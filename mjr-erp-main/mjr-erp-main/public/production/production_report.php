<?php
/**
 * Production Report (Screen 3)
 * Comprehensive dashboard for production metrics, costs, and efficiency.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
if (!has_permission('view_reports')) {
    set_flash('You do not have permission to view reports.', 'danger');
    redirect('../../index.php');
}

$page_title = 'Production Report - MJR Group ERP';
$company_id = active_company_id(1);
$company_name = active_company_name('Current Company');

// Fetch dropdown data for filters
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
$categories = db_fetch_all("SELECT id, name FROM categories ORDER BY name");
$products = db_fetch_all("SELECT id, code, name FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<style>
html[data-bs-theme="dark"],
html[data-app-theme="dark"] {
    --pr-panel-bg: #212529;
    --pr-panel-border: rgba(255,255,255,0.12);
    --pr-text: #f8fafc;
    --pr-muted: #9aa4b2;
    --pr-tab-bg: #212529;
}

html[data-bs-theme="light"],
html[data-app-theme="light"] {
    --pr-panel-bg: #ffffff;
    --pr-panel-border: #d0d7de;
    --pr-text: #212529;
    --pr-muted: #5f6b7a;
    --pr-tab-bg: #f8f9fa;
}

#productionReportPage .bg-dark {
    background-color: var(--pr-panel-bg) !important;
}

#productionReportPage .border-secondary {
    border-color: var(--pr-panel-border) !important;
}

#productionReportPage .text-white,
#productionReportPage .card-header,
#productionReportPage .card-header h6,
#productionReportPage .card-body,
#productionReportPage .nav-link,
#productionReportPage .table-dark,
#productionReportPage .table-dark th,
#productionReportPage .table-dark td {
    color: var(--pr-text) !important;
}

#productionReportPage .text-muted,
#productionReportPage .form-label.text-muted,
#productionReportPage .small.text-muted,
#productionReportPage .text-secondary {
    color: var(--pr-muted) !important;
}

#productionReportPage .form-control.bg-dark,
#productionReportPage .form-select.bg-dark,
#productionReportPage input.bg-dark,
#productionReportPage select.bg-dark {
    background-color: var(--pr-panel-bg) !important;
    border-color: var(--pr-panel-border) !important;
    color: var(--pr-text) !important;
}

#productionReportPage .nav-link.bg-dark {
    background-color: var(--pr-tab-bg) !important;
    border-color: var(--pr-panel-border) !important;
    color: var(--pr-text) !important;
}

#productionReportPage .nav-link.active {
    border-bottom-color: transparent !important;
}

#productionReportPage .table-dark,
#productionReportPage .table.table-dark {
    --bs-table-bg: var(--pr-panel-bg);
    --bs-table-color: var(--pr-text);
    --bs-table-border-color: var(--pr-panel-border);
}
</style>

<div class="container-fluid" id="productionReportPage">
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h2 class="mb-0 text-white"><i class="fas fa-chart-line me-2 text-info"></i>Production Report</h2>
            <div class="text-muted small mt-1">Showing data for <strong><?= escape_html($company_name) ?></strong></div>
        </div>
        <div class="col-md-6 text-end">
            <!-- 9️⃣ EXPORT & PRINT BUTTONS -->
            <button type="button" class="btn btn-outline-success me-2" onclick="exportData('excel')">
                <i class="fas fa-file-excel me-1"></i> Export Excel
            </button>
            <button type="button" class="btn btn-outline-danger me-2" onclick="exportData('pdf')">
                <i class="fas fa-file-pdf me-1"></i> Export PDF
            </button>
            <button type="button" class="btn btn-outline-light" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print Report
            </button>
        </div>
    </div>

    <!-- 1️⃣ PRODUCTION REPORT FILTERS -->
    <div class="card bg-dark border-secondary mb-4 print-hide">
        <div class="card-header bg-primary bg-opacity-10 py-3">
            <h5 class="mb-0 text-primary"><i class="fas fa-filter me-2"></i>Production Report Filters</h5>
        </div>
        <div class="card-body">
            <form id="filterForm" class="row g-3" onsubmit="event.preventDefault(); loadReportData();">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Company / Subsidiary</label>
                    <select class="form-select bg-dark border-secondary text-white" id="filterCompany" name="company">
                        <option value="<?= $company_id ?>" selected><?= escape_html($company_name) ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Production Location</label>
                    <select class="form-select bg-dark border-secondary text-white" id="filterLocation" name="location_id">
                        <option value="">All Locations</option>
                        <?php foreach($locations as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= escape_html($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Product Category</label>
                    <select class="form-select bg-dark border-secondary text-white" id="filterCategory" name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= escape_html($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Product</label>
                    <select class="form-select select2" id="filterProduct" name="product_id">
                        <option value="">All Products</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= escape_html($p['code'] . ' - ' . $p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Production Order No.</label>
                    <input type="text" class="form-control bg-dark border-secondary text-white" id="filterOrder" name="order_no" placeholder="Search order reference...">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Date From</label>
                    <input type="date" class="form-control bg-dark border-secondary text-white" id="filterDateFrom" name="date_from" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Date To</label>
                    <input type="date" class="form-control bg-dark border-secondary text-white" id="filterDateTo" name="date_to" value="<?= date('Y-m-t') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Production Status</label>
                    <select class="form-select bg-dark border-secondary text-white" id="filterStatus" name="status">
                        <option value="">All Statuses</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="col-12 text-end mt-3">
                    <button type="button" class="btn btn-outline-secondary me-2" onclick="resetFilters()">Reset Filters</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync me-2"></i>Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2️⃣ PRODUCTION SUMMARY DASHBOARD -->
    <h5 class="text-secondary mb-3"><i class="fas fa-tachometer-alt me-2 text-warning"></i>Production Summary Dashboard</h5>
    <div class="row mb-4">
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Total Production Orders</div>
                    <h3 class="text-white mt-2 mb-0" id="dashOrders">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Total Units Produced</div>
                    <h3 class="text-info mt-2 mb-0" id="dashUnits">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Total Production Cost</div>
                    <h3 class="text-danger mt-2 mb-0" id="dashTotalCost">$0.00</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Average Cost Per Unit</div>
                    <h3 class="text-warning mt-2 mb-0" id="dashAvgCost">$0.00</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Pending Production Orders</div>
                    <h3 class="text-secondary mt-2 mb-0" id="dashPendingOrders">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Production Efficiency</div>
                    <h3 class="text-success mt-2 mb-0" id="dashEfficiency">0%</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN REPORT TABS -->
    <ul class="nav nav-tabs border-secondary mb-4 print-hide" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active bg-dark text-white border-secondary border-bottom-0" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders-pane" type="button" role="tab">Production Orders</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link bg-dark text-white border-secondary" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials-pane" type="button" role="tab">Material Consumption</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link bg-dark text-white border-secondary" id="costs-tab" data-bs-toggle="tab" data-bs-target="#costs-pane" type="button" role="tab">Cost Analysis</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link bg-dark text-white border-secondary" id="charts-tab" data-bs-toggle="tab" data-bs-target="#charts-pane" type="button" role="tab">Trend Charts</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link bg-dark text-white border-secondary" id="efficiency-tab" data-bs-toggle="tab" data-bs-target="#efficiency-pane" type="button" role="tab">Efficiency & History</button>
        </li>
    </ul>

    <div class="tab-content" id="reportTabsContent">
        
        <!-- 3️⃣ PRODUCTION ORDER REPORT TABLE -->
        <div class="tab-pane fade show active print-show" id="orders-pane" role="tabpanel" tabindex="0">
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Production Order Report Table</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover table-bordered border-secondary" id="ordersTable" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Order No</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Planned Qty</th>
                                    <th>Produced Qty</th>
                                    <th>Remaining Qty</th>
                                    <th>Production Cost</th>
                                    <th>Start Date</th>
                                    <th>Completion Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4️⃣ RAW MATERIAL CONSUMPTION REPORT -->
        <div class="tab-pane fade print-show" id="materials-pane" role="tabpanel" tabindex="0">
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header border-secondary">
                    <h6 class="mb-0">Raw Material Consumption Report</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover table-bordered border-secondary" id="materialsTable" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Material Code</th>
                                    <th>Material Name</th>
                                    <th>Category</th>
                                    <th>Total Qty Used</th>
                                    <th>Unit</th>
                                    <th>Total Cost</th>
                                    <th>Linked Product(s)</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5️⃣ PRODUCTION COST ANALYSIS -->
        <div class="tab-pane fade print-show" id="costs-pane" role="tabpanel" tabindex="0">
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header border-secondary">
                    <h6 class="mb-0">Production Cost Analysis</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover table-bordered border-secondary" id="costsTable" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Material Cost (\$)</th>
                                    <th>Labor Cost (\$)</th>
                                    <th>Electricity Cost (\$)</th>
                                    <th>Machine Cost (\$)</th>
                                    <th>Total Production Cost (\$)</th>
                                    <th>Avg Cost Per Unit (\$)</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6️⃣ PRODUCTION TREND CHART -->
        <div class="tab-pane fade print-show" id="charts-pane" role="tabpanel" tabindex="0">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card bg-dark border-secondary h-100">
                        <div class="card-header border-secondary">
                            <h6 class="mb-0">Production Volume (Monthly)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="volumeChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card bg-dark border-secondary h-100">
                        <div class="card-header border-secondary">
                            <h6 class="mb-0">Production Cost Trend</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="costChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7️⃣ & 8️⃣ EFFICIENCY AND HISTORY -->
        <div class="tab-pane fade print-show" id="efficiency-pane" role="tabpanel" tabindex="0">
            <div class="row">
                <!-- 7️⃣ PRODUCTION EFFICIENCY REPORT -->
                <div class="col-12 mb-4">
                    <div class="card bg-dark border-secondary">
                        <div class="card-header border-secondary">
                            <h6 class="mb-0">Production Efficiency Report</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-dark table-hover table-bordered border-secondary" id="efficiencyTable" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Planned Qty</th>
                                            <th>Actual Produced Qty</th>
                                            <th>Production Variance</th>
                                            <th>Efficiency %</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 8️⃣ PRODUCTION HISTORY PANEL -->
                <div class="col-12 mb-4">
                    <div class="card bg-dark border-secondary">
                        <div class="card-header border-secondary">
                            <h6 class="mb-0">Completed Production History</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-dark table-hover table-bordered border-secondary" id="historyTable" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Completion Ref</th>
                                            <th>Production Order</th>
                                            <th>Product</th>
                                            <th>Quantity Produced</th>
                                            <th>Completion Date</th>
                                            <th>Supervisor</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Export Buttons Scripts for DataTables -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #productionReportPage, #productionReportPage * {
        visibility: visible;
    }
    #productionReportPage {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        color: black !important;
        background: white !important;
    }
    .print-hide, .navbar, .sidebar, .btn {
        display: none !important;
    }
    .print-show {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    .tab-pane {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
        page-break-after: always;
    }
    .card, .table {
        border-color: #ddd !important;
    }
    .text-white, .text-info, .text-warning, .text-danger, .text-success {
        color: black !important;
    }
    .bg-dark {
        background-color: transparent !important;
    }
}
</style>

<script>
let dtOrders, dtMaterials, dtCosts, dtEfficiency, dtHistory;
let volChart, costTrendChart;
let reportDtOptions;
let currentReportData = {
    orders: [],
    materials: [],
    costs: [],
    efficiency: [],
    history: []
};

function renderPlainRows(tbodySelector, rows) {
    const tbody = document.querySelector(tbodySelector);
    if (!tbody) return;
    tbody.innerHTML = rows.map(cols => `<tr>${cols.map(col => `<td>${col ?? ''}</td>`).join('')}</tr>`).join('');
}

function initDataTable(selector) {
    if (!window.jQuery || !$.fn || !$.fn.DataTable) return null;
    const $table = $(selector);
    if (!$table.length) return null;

    if ($.fn.dataTable.isDataTable(selector)) {
        return $table.DataTable();
    }

    try {
        return $table.DataTable(reportDtOptions);
    } catch (e) {
        console.error(`DataTable init failed for ${selector}`, e);
        return null;
    }
}

function ensureReportTables() {
    dtOrders = dtOrders || initDataTable('#ordersTable');
    dtMaterials = dtMaterials || initDataTable('#materialsTable');
    dtCosts = dtCosts || initDataTable('#costsTable');
    dtEfficiency = dtEfficiency || initDataTable('#efficiencyTable');
    dtHistory = dtHistory || initDataTable('#historyTable');
}

$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap-5' });

    // Initialize all DataTables
    reportDtOptions = {
        dom: 'Brtip',
        buttons: ['excelHtml5', 'pdfHtml5', 'print'],
        pageLength: 20,
        lengthChange: false,
        searching: false // we use our own custom filters
    };

    ensureReportTables();

    // Initial Load
    loadReportData();
});

function resetFilters() {
    $('#filterForm')[0].reset();
    $('.select2').val(null).trigger('change');
    // Set dates back to defaults manually if needed
    loadReportData();
}

function exportData(type) {
    let activePane = $('.tab-pane.active').attr('id');

    const exportMap = {
        'orders-pane': {
            title: 'Production Orders',
            headers: ['Order No', 'Product', 'Category', 'Planned Qty', 'Produced Qty', 'Remaining Qty', 'Production Cost', 'Start Date', 'Completion Date', 'Status'],
            rows: currentReportData.orders.map(o => [o.wo_number, o.product, o.category, o.planned_qty, o.produced_qty, o.remaining_qty, Number(o.total_cost || 0).toFixed(2), o.start_date || '-', o.completion_date || '-', o.status])
        },
        'materials-pane': {
            title: 'Raw Material Consumption',
            headers: ['Material Code', 'Material Name', 'Category', 'Total Qty Used', 'Unit', 'Total Cost', 'Linked Product(s)'],
            rows: currentReportData.materials.map(m => [m.code, m.name, m.category || '-', m.qty_used, m.unit || 'pcs', Number(m.total_cost || 0).toFixed(2), m.linked_products || '-'])
        },
        'costs-pane': {
            title: 'Production Cost Analysis',
            headers: ['Product', 'Material Cost', 'Labor Cost', 'Electricity Cost', 'Machine Cost', 'Total Production Cost', 'Avg Cost Per Unit'],
            rows: currentReportData.costs.map(c => [c.product, Number(c.material_cost || 0).toFixed(2), Number(c.labor_cost || 0).toFixed(2), Number(c.electricity_cost || 0).toFixed(2), Number(c.machine_cost || 0).toFixed(2), Number(c.total_cost || 0).toFixed(2), Number(c.avg_cost || 0).toFixed(2)])
        },
        'efficiency-pane': {
            title: 'Production Efficiency',
            headers: ['Product', 'Planned Qty', 'Actual Produced Qty', 'Production Variance', 'Efficiency %'],
            rows: currentReportData.efficiency.map(e => [e.product, e.planned, e.actual, e.variance, Number(e.eff_percent || 0).toFixed(1) + '%'])
        }
    };

    const selected = exportMap[activePane];
    if (!selected) {
        alert("Exports are bound to the currently visible table.");
        return;
    }

    if (!selected.rows.length) {
        alert("No data to export. Generate report first.");
        return;
    }

    if (type === 'excel') {
        downloadCsv(selected.headers, selected.rows, `${selected.title.replace(/\s+/g, '_').toLowerCase()}.csv`);
        return;
    }

    if (type === 'pdf') {
        openPrintPreview(selected.title, selected.headers, selected.rows);
        return;
    }
}

function csvEscape(value) {
    const v = value == null ? '' : String(value);
    return `"${v.replace(/"/g, '""')}"`;
}

function downloadCsv(headers, rows, filename) {
    const lines = [headers.map(csvEscape).join(',')];
    rows.forEach(r => lines.push(r.map(csvEscape).join(',')));
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

function openPrintPreview(title, headers, rows) {
    const htmlRows = rows.map(r => `<tr>${r.map(c => `<td>${c ?? ''}</td>`).join('')}</tr>`).join('');
    const html = `
        <html>
            <head>
                <title>${title}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h2 { margin-bottom: 12px; }
                    table { width: 100%; border-collapse: collapse; font-size: 12px; }
                    th, td { border: 1px solid #444; padding: 6px; text-align: left; }
                    th { background: #f2f2f2; }
                </style>
            </head>
            <body>
                <h2>${title}</h2>
                <table>
                    <thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
                    <tbody>${htmlRows}</tbody>
                </table>
            </body>
        </html>
    `;

    const w = window.open('', '_blank');
    if (!w) {
        alert('Pop-up blocked. Allow pop-ups to export PDF.');
        return;
    }
    w.document.open();
    w.document.write(html);
    w.document.close();
    w.focus();
    w.print();
}

function loadReportData() {
    ensureReportTables();

    const filters = {
        company: $('#filterCompany').val(),
        location_id: $('#filterLocation').val(),
        category_id: $('#filterCategory').val(),
        product_id: $('#filterProduct').val(),
        order_no: $('#filterOrder').val(),
        date_from: $('#filterDateFrom').val(),
        date_to: $('#filterDateTo').val(),
        status: $('#filterStatus').val()
    };

    const query = new URLSearchParams(filters).toString();

    fetch('production_report_ajax.php?' + query, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(async (res) => {
            const raw = await res.text();
            let data = null;
            try {
                data = JSON.parse(raw);
            } catch (e) {
                const snippet = (raw || '').trim().slice(0, 300);
                throw new Error(snippet || `Unexpected server response (HTTP ${res.status})`);
            }

            if (!res.ok) {
                throw new Error(data.message || `Request failed (HTTP ${res.status})`);
            }

            return data;
        })
        .then(data => {
            if (data.success) {
                currentReportData.orders = Array.isArray(data.orders) ? data.orders : [];
                currentReportData.materials = Array.isArray(data.materials) ? data.materials : [];
                currentReportData.costs = Array.isArray(data.costs) ? data.costs : [];
                currentReportData.efficiency = Array.isArray(data.efficiency) ? data.efficiency : [];
                currentReportData.history = Array.isArray(data.history) ? data.history : [];

                renderDashboard(data.dashboard);
                renderOrders(data.orders);
                renderMaterials(data.materials);
                renderCosts(data.costs);
                renderCharts(data.charts);
                renderEfficiency(data.efficiency);
                renderHistory(data.history);
                
                // Redraw tables slightly delayed inside tabs
                setTimeout(() => {
                    if (dtOrders && dtOrders.columns) dtOrders.columns.adjust();
                    if (dtMaterials && dtMaterials.columns) dtMaterials.columns.adjust();
                    if (dtCosts && dtCosts.columns) dtCosts.columns.adjust();
                    if (dtEfficiency && dtEfficiency.columns) dtEfficiency.columns.adjust();
                }, 200);
            } else {
                alert("Error loading report: " + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Failed to load report data: " + (err.message || "Unknown error"));
        });
}

function renderDashboard(data) {
    $('#dashOrders').text(data.totalOrders);
    $('#dashUnits').text(data.totalUnits);
    $('#dashTotalCost').text('$' + parseFloat(data.totalCost).toFixed(2));
    $('#dashAvgCost').text('$' + parseFloat(data.avgCost).toFixed(2));
    $('#dashPendingOrders').text(data.pendingOrders);
    $('#dashEfficiency').text(parseFloat(data.overallEfficiency).toFixed(1) + '%');
}

function renderOrders(orders) {
    if (!dtOrders) {
        renderPlainRows('#ordersTable tbody', orders.map(o => {
            let badge = o.status == 'completed' ? 'success' : (o.status == 'in_progress' ? 'primary' : 'warning');
            return [
                `<strong>${o.wo_number}</strong>`,
                o.product,
                o.category,
                o.planned_qty,
                o.produced_qty,
                o.remaining_qty,
                `$${parseFloat(o.total_cost).toFixed(2)}`,
                o.start_date || '-',
                o.completion_date || '-',
                `<span class="badge bg-${badge}">${o.status}</span>`
            ];
        }));
        return;
    }

    dtOrders.clear();
    orders.forEach(o => {
        let badge = o.status == 'completed' ? 'success' : (o.status == 'in_progress' ? 'primary' : 'warning');
        dtOrders.row.add([
            `<strong>${o.wo_number}</strong>`,
            o.product,
            o.category,
            o.planned_qty,
            o.produced_qty,
            o.remaining_qty,
            `$${parseFloat(o.total_cost).toFixed(2)}`,
            o.start_date || '-',
            o.completion_date || '-',
            `<span class="badge bg-${badge}">${o.status}</span>`
        ]);
    });
    dtOrders.draw();
}

function renderMaterials(mats) {
    if (!dtMaterials) {
        renderPlainRows('#materialsTable tbody', mats.map(m => [
            m.code,
            m.name,
            m.category || '-',
            m.qty_used,
            m.unit || 'pcs',
            `$${parseFloat(m.total_cost).toFixed(2)}`,
            m.linked_products
        ]));
        return;
    }

    dtMaterials.clear();
    mats.forEach(m => {
        dtMaterials.row.add([
            m.code,
            m.name,
            m.category || '-',
            m.qty_used,
            m.unit || 'pcs',
            `$${parseFloat(m.total_cost).toFixed(2)}`,
            m.linked_products
        ]);
    });
    dtMaterials.draw();
}

function renderCosts(costs) {
    if (!dtCosts) {
        renderPlainRows('#costsTable tbody', costs.map(c => [
            c.product,
            `$${parseFloat(c.material_cost).toFixed(2)}`,
            `$${parseFloat(c.labor_cost).toFixed(2)}`,
            `$${parseFloat(c.electricity_cost).toFixed(2)}`,
            `$${parseFloat(c.machine_cost).toFixed(2)}`,
            `$${parseFloat(c.total_cost).toFixed(2)}`,
            `$${parseFloat(c.avg_cost).toFixed(2)}`
        ]));
        return;
    }

    dtCosts.clear();
    costs.forEach(c => {
        dtCosts.row.add([
            c.product,
            `$${parseFloat(c.material_cost).toFixed(2)}`,
            `$${parseFloat(c.labor_cost).toFixed(2)}`,
            `$${parseFloat(c.electricity_cost).toFixed(2)}`,
            `$${parseFloat(c.machine_cost).toFixed(2)}`,
            `$${parseFloat(c.total_cost).toFixed(2)}`,
            `$${parseFloat(c.avg_cost).toFixed(2)}`
        ]);
    });
    dtCosts.draw();
}

function renderCharts(cdata) {
    // Volume Chart
    if (volChart) volChart.destroy();
    volChart = new Chart(document.getElementById('volumeChart'), {
        type: 'bar',
        data: {
            labels: cdata.labels,
            datasets: [{
                label: 'Quantity Produced',
                data: cdata.volumes,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Cost Chart
    if (costTrendChart) costTrendChart.destroy();
    costTrendChart = new Chart(document.getElementById('costChart'), {
        type: 'line',
        data: {
            labels: cdata.labels,
            datasets: [{
                label: 'Avg Cost Per Unit',
                data: cdata.costs,
                fill: false,
                borderColor: 'rgb(255, 99, 132)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

function renderEfficiency(eff) {
    if (!dtEfficiency) {
        renderPlainRows('#efficiencyTable tbody', eff.map(e => [
            e.product,
            e.planned,
            e.actual,
            e.variance,
            `${parseFloat(e.eff_percent).toFixed(1)}%`
        ]));
        return;
    }

    dtEfficiency.clear();
    eff.forEach(e => {
        dtEfficiency.row.add([
            e.product,
            e.planned,
            e.actual,
            e.variance,
            `${parseFloat(e.eff_percent).toFixed(1)}%`
        ]);
    });
    dtEfficiency.draw();
}

function renderHistory(hist) {
    if (!dtHistory) {
        renderPlainRows('#historyTable tbody', hist.map(h => [
            h.completion_ref,
            h.wo_number,
            h.product,
            h.qty_produced,
            h.completion_date,
            h.supervisor
        ]));
        return;
    }

    dtHistory.clear();
    hist.forEach(h => {
        dtHistory.row.add([
            h.completion_ref,
            h.wo_number,
            h.product,
            h.qty_produced,
            h.completion_date,
            h.supervisor
        ]);
    });
    dtHistory.draw();
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
