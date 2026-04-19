<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();

$page_title = 'Add New Warehouse - MJR Group';
include __DIR__ . '/../../../../templates/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Warehouses</a></li>
                <li class="breadcrumb-item active">Add New</li>
              </ol>
            </nav>

            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Register New Warehouse</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="create_process.php">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">Warehouse Name *</label>
                                <input type="text" name="name" id="warehouse_name" class="form-control" placeholder="e.g. Main Central Warehouse" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Manager Name</label>
                                <input type="text" name="manager_name" class="form-control" placeholder="Person in charge">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Capacity (sq. ft)</label>
                                <input type="number" name="capacity" class="form-control" placeholder="e.g. 5000">
                            </div>

                            <div class="col-md-12 mb-4">
                                <label class="form-label">Full Address / Location</label>
                                <textarea name="location" class="form-control" rows="3" placeholder="Enter physical address..."></textarea>
                            </div>

                            <!-- Bin Transfer Section -->
                            <div class="col-md-12 mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <h6 class="mb-0 text-primary fw-bold">Transfer Existing Bins from Other Warehouses</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-3 rounded-circle" id="toggleBinSection" title="Add/Transfer Bins">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div id="binTransferSection" style="display: none;" class="p-3 border rounded bg-light bg-opacity-10 border-info border-opacity-25">
                                    <p class="small text-muted mb-3">Select bins from existing warehouses to reassign them to this new warehouse. All stock in these bins will be moved automatically.</p>
                                    <div class="row" id="binsList">
                                        <!-- Bins will be populated here via AJAX -->
                                        <div class="col-12 text-center py-3">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                            <span class="ms-2 small text-muted">Loading available bins...</span>
                                        </div>
                                    </div>

                                    <hr class="my-4 border-info opacity-25">

                                    <div class="d-flex align-items-center mb-3">
                                        <h6 class="mb-0 text-success fw-bold">Create New Bins</h6>
                                        <button type="button" class="btn btn-sm btn-outline-success ms-3 rounded-pill" id="addNewBinRow">
                                            <i class="fas fa-plus me-1"></i>Add Bin
                                        </button>
                                    </div>
                                    <div id="newBinsContainer" class="row">
                                        <div class="col-md-4 mb-2 bin-input-row">
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="new_bins[]" class="form-control" placeholder="Bin Name (e.g. A-101)">
                                                <button class="btn btn-outline-danger remove-bin-row" type="button"><i class="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">Save Warehouse</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let binsLoaded = false;

    $('#toggleBinSection').click(function() {
        $('#binTransferSection').slideToggle();
        $(this).find('i').toggleClass('fa-plus fa-minus');
        
        if (!binsLoaded) {
            loadAllBins();
        }
    });

    function loadAllBins() {
        $.ajax({
            url: '../../ajax_get_all_bins.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                let html = '';
                if (data.length === 0) {
                    html = '<div class="col-12 text-center text-muted small py-2">No existing bins found in other warehouses.</div>';
                } else {
                    // Group by warehouse
                    let grouped = {};
                    data.forEach(item => {
                        if (!grouped[item.warehouse_name]) {
                            grouped[item.warehouse_name] = [];
                        }
                        grouped[item.warehouse_name].push(item);
                    });

                    for (let whName in grouped) {
                        html += `<div class="col-12 mt-2"><strong class="small text-secondary text-uppercase">${whName}</strong></div>`;
                        grouped[whName].forEach(bin => {
                            let value = bin.warehouse_id + ':' + bin.bin_location;
                            html += `
                                <div class="col-md-4 col-sm-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="selected_bins[]" value="${value}" id="bin_${bin.warehouse_id}_${bin.bin_location.replace(/\s+/g, '_')}">
                                        <label class="form-check-label small" for="bin_${bin.warehouse_id}_${bin.bin_location.replace(/\s+/g, '_')}">
                                            ${bin.bin_location}
                                        </label>
                                    </div>
                                </div>
                            `;
                        });
                    }
                }
                $('#binsList').html(html);
                binsLoaded = true;
            },
            error: function() {
                $('#binsList').html('<div class="col-12 text-center text-danger small py-2">Failed to load bins. Please try again.</div>');
            }
        });
    }

    // New Bins logic
    $('#addNewBinRow').click(function() {
        const row = `
            <div class="col-md-4 mb-2 bin-input-row">
                <div class="input-group input-group-sm">
                    <input type="text" name="new_bins[]" class="form-control" placeholder="Bin Name">
                    <button class="btn btn-outline-danger remove-bin-row" type="button"><i class="fas fa-times"></i></button>
                </div>
            </div>
        `;
        $('#newBinsContainer').append(row);
    });

    $(document).on('click', '.remove-bin-row', function() {
        if ($('.bin-input-row').length > 1) {
            $(this).closest('.bin-input-row').remove();
        } else {
            $(this).siblings('input').val('');
        }
    });
});
</script>

<?php include __DIR__ . '/../../../../templates/footer.php'; ?>