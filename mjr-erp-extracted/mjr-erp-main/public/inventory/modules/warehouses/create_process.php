<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // connection variable ko access karne ke liye (agar functions.php me defined hai)
    global $conn;

    // Get current company_id
    $company_id = $_SESSION['company_id'] ?? 1;

    // Extract POST variables
    $name = trim($_POST['name'] ?? '');
    $manager = trim($_POST['manager_name'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $location = trim($_POST['location'] ?? '');

    if ($name === '') {
        die("Error: Warehouse Name is required.");
    }

    // Generate a code for the location (e.g., WH-NAME)
    $base_code = 'WH-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 10));
    $code = $base_code;
    $count = 1;
    
    // Ensure code is unique in locations table
    while (db_fetch("SELECT id FROM locations WHERE code = ?", [$code])) {
        $code = $base_code . $count++;
    }

    try {
        db_begin_transaction();

        // 1. Create entry in locations table
        $location_sql = "INSERT INTO locations (code, name, company_id, type, address, is_active, created_at) 
                         VALUES (?, ?, ?, 'warehouse', ?, 1, NOW())";
        $location_id = db_insert($location_sql, [$code, $name, $company_id, $location]);

        // 2. Create entry in warehouses table
        $wh_sql = "INSERT INTO warehouses (name, manager_name, capacity, location, location_id, company_id, is_active) 
                   VALUES (?, ?, ?, ?, ?, ?, 1)";
        $warehouse_id = db_insert($wh_sql, [$name, $manager, $capacity, $location, $location_id, $company_id]);

        if ($warehouse_id) {
            // 3. Handle Bin Reassignment if any selected
            $selected_bins = $_POST['selected_bins'] ?? [];
            if (!empty($selected_bins)) {
                foreach ($selected_bins as $bin_data) {
                    $parts = explode(':', $bin_data, 2);
                    if (count($parts) === 2) {
                        $old_wh_id = (int)$parts[0];
                        $bin_code = $parts[1]; // Old UI sends code, we should update the bin record

                        $old_wh = db_fetch("SELECT location_id FROM warehouses WHERE id = ?", [$old_wh_id]);
                        $old_loc_id = $old_wh ? $old_wh['location_id'] : null;

                        if ($old_loc_id) {
                            // Find the actual bin record
                            $bin_record = db_fetch("SELECT id FROM bins WHERE warehouse_id = ? AND code = ?", [$old_wh_id, $bin_code]);
                            if ($bin_record) {
                                $bin_id = $bin_record['id'];
                                
                                // Move the bin itself to the new warehouse
                                db_query("UPDATE bins SET warehouse_id = ? WHERE id = ?", [$warehouse_id, $bin_id]);

                                // Update the associations
                                db_query(
                                    "UPDATE warehouse_inventory SET warehouse_id = ? WHERE warehouse_id = ? AND bin_id = ?",
                                    [$warehouse_id, $old_wh_id, $bin_id]
                                );
                                db_query(
                                    "UPDATE inventory_stock_levels SET location_id = ? WHERE location_id = ? AND bin_id = ?",
                                    [$location_id, $old_loc_id, $bin_id]
                                );
                            }
                        }
                    }
                }
            }

            // 4. Handle New Bin Creation
            $new_bins = $_POST['new_bins'] ?? [];
            if (!empty($new_bins)) {
                foreach ($new_bins as $bin_name) {
                    $bin_name = trim($bin_name);
                    if ($bin_name !== '') {
                        // Create proper records in the bins table
                        db_query(
                            "INSERT INTO bins (warehouse_id, code, is_active) 
                             VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_active = 1",
                            [$warehouse_id, $bin_name]
                        );
                    }
                }
            }

            db_commit();
            header("Location: index.php?msg=success");
            exit;
        } else {
            throw new Exception("Could not save warehouse record.");
        }
    } catch (Exception $e) {
        db_rollback();
        error_log("Warehouse create error: " . $e->getMessage());
        die("Error: " . $e->getMessage());
    }
}


