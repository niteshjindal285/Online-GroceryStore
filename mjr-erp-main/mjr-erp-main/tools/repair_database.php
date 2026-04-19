<?php
/**
 * Idempotent database repair utility for common schema drift issues.
 *
 * Run with:
 *   C:\xampp\php\php.exe tools\repair_database.php
 */

require_once __DIR__ . '/../includes/database.php';

function repair_log($message) {
    echo $message . PHP_EOL;
}

function repair_run($label, $sql) {
    repair_log('[RUN] ' . $label);
    db_query($sql);
}

function repair_try($label, $sql) {
    try {
        repair_run($label, $sql);
    } catch (Throwable $e) {
        repair_log('[SKIP] ' . $label . ' -> ' . $e->getMessage());
    }
}

repair_log('Starting database repair...');

if (db_table_exists('users')) {
    if (db_table_has_column('users', 'active') && !db_table_has_column('users', 'is_active')) {
        repair_try('Rename users.active to users.is_active', "ALTER TABLE users CHANGE COLUMN active is_active TINYINT(1) NOT NULL DEFAULT 1");
    } elseif (!db_table_has_column('users', 'is_active')) {
        repair_try('Add users.is_active', "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    }
}

if (db_table_exists('work_orders') && !db_table_has_column('work_orders', 'active')) {
    repair_try('Add work_orders.active', "ALTER TABLE work_orders ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
}

if (db_table_exists('accounts') && !db_table_has_column('accounts', 'updated_at')) {
    repair_try('Add accounts.updated_at', "ALTER TABLE accounts ADD COLUMN updated_at DATETIME NULL DEFAULT NULL");
    repair_try('Backfill accounts.updated_at', "UPDATE accounts SET updated_at = created_at WHERE updated_at IS NULL");
}

if (db_table_exists('journal_entries') && !db_table_has_column('journal_entries', 'updated_at')) {
    repair_try('Add journal_entries.updated_at', "ALTER TABLE journal_entries ADD COLUMN updated_at DATETIME NULL DEFAULT NULL");
    repair_try('Backfill journal_entries.updated_at', "UPDATE journal_entries SET updated_at = created_at WHERE updated_at IS NULL");
}

if (db_table_exists('inventory_items')) {
    if (!db_table_has_column('inventory_items', 'reorder_level')) {
        repair_try('Add inventory_items.reorder_level', "ALTER TABLE inventory_items ADD COLUMN reorder_level INT NOT NULL DEFAULT 0");
    }
    if (!db_table_has_column('inventory_items', 'reorder_quantity')) {
        repair_try('Add inventory_items.reorder_quantity', "ALTER TABLE inventory_items ADD COLUMN reorder_quantity INT NOT NULL DEFAULT 0");
    }
}

if (db_table_exists('locations') && !db_table_has_column('locations', 'is_active')) {
    repair_try('Add locations.is_active', "ALTER TABLE locations ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
}

foreach ([
    'customers' => "ALTER TABLE customers ADD COLUMN company_id INT NULL DEFAULT 1",
    'sales_orders' => "ALTER TABLE sales_orders ADD COLUMN company_id INT NULL DEFAULT 1",
    'quotes' => "ALTER TABLE quotes ADD COLUMN company_id INT NULL DEFAULT 1",
    'purchase_orders' => "ALTER TABLE purchase_orders ADD COLUMN company_id INT NULL DEFAULT 1",
    'suppliers' => "ALTER TABLE suppliers ADD COLUMN company_id INT NULL DEFAULT 1",
] as $table => $sql) {
    if (db_table_exists($table) && !db_table_has_column($table, 'company_id')) {
        repair_try('Add ' . $table . '.company_id', $sql);
    }
}

if (db_table_exists('project_stages')) {
    foreach ([
        "ALTER TABLE project_stages ADD COLUMN due_date DATE NULL",
        "ALTER TABLE project_stages ADD COLUMN status ENUM('pending','in_progress','complete','invoiced','paid') DEFAULT 'pending'",
        "ALTER TABLE project_stages ADD COLUMN invoice_number VARCHAR(50) NULL",
        "ALTER TABLE project_stages ADD COLUMN invoiced_at DATETIME NULL",
        "ALTER TABLE project_stages ADD COLUMN sort_order INT NOT NULL DEFAULT 0",
    ] as $sql) {
        repair_try('Update project_stages schema', $sql);
    }
}

if (!db_table_exists('project_invoices') && db_table_exists('projects') && db_table_exists('users')) {
    repair_try('Create project_invoices', "
        CREATE TABLE project_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            stage_id INT NULL,
            invoice_number VARCHAR(50) NOT NULL,
            amount DECIMAL(15,2) DEFAULT 0.00,
            tax_amount DECIMAL(15,2) DEFAULT 0.00,
            total_amount DECIMAL(15,2) DEFAULT 0.00,
            status ENUM('draft','sent','paid','cancelled') DEFAULT 'draft',
            issued_date DATE NULL,
            due_date DATE NULL,
            paid_date DATE NULL,
            notes TEXT NULL,
            created_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project_id (project_id),
            INDEX idx_stage_id (stage_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

repair_log('Database repair finished.');
