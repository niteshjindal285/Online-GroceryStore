<?php
require_once __DIR__ . '/../includes/database.php';

try {
    $pdo = db_connect();

    // 1. Add Purchase Unit & Conversion Factor to Inventory Items
    $check_purchase_unit = $pdo->query("SHOW COLUMNS FROM inventory_items LIKE 'purchase_unit_id'");
    if ($check_purchase_unit->rowCount() == 0) {
        $pdo->exec("ALTER TABLE inventory_items 
            ADD COLUMN purchase_unit_id INT NULL AFTER unit_of_measure_id,
            ADD COLUMN purchase_conversion_factor DECIMAL(15,6) DEFAULT 1.000000 AFTER purchase_unit_id");
        $pdo->exec("ALTER TABLE inventory_items 
            ADD CONSTRAINT fk_inv_items_pur_unit 
            FOREIGN KEY (purchase_unit_id) REFERENCES units_of_measure(id) ON DELETE SET NULL");
        echo "Added purchase_unit_id to inventory_items.\n";
    }

    // 2. Add Requisitions Tables
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS purchase_requisitions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requisition_number VARCHAR(50) NOT NULL UNIQUE,
        request_date DATE NOT NULL,
        required_date DATE NULL,
        department VARCHAR(100) NULL,
        status VARCHAR(20) DEFAULT 'draft',
        total_estimated_amount DECIMAL(15,2) DEFAULT 0.00,
        notes TEXT NULL,
        po_id INT NULL,
        created_by INT NOT NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "purchase_requisitions created.\n";

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS purchase_requisition_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requisition_id INT NOT NULL,
        item_id INT NOT NULL,
        quantity DECIMAL(15,4) NOT NULL,
        estimated_unit_price DECIMAL(15,4) DEFAULT 0.00,
        estimated_line_total DECIMAL(15,2) DEFAULT 0.00,
        FOREIGN KEY (requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "purchase_requisition_lines created.\n";

    echo "Upgrade completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
