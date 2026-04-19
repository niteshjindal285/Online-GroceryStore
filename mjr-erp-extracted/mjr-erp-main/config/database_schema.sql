-- ================================================================
-- MJR Group ERP System - MySQL Database Schema
-- Generated from Flask SQLAlchemy Models
-- Date: 2025-10-03
-- ================================================================

SET FOREIGN_KEY_CHECKS=0;
DROP DATABASE IF EXISTS mjr_group_erp;
CREATE DATABASE mjr_group_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mjr_group_erp;

-- ================================================================
-- Core Tables
-- ================================================================

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(256) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    company_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_company_id (company_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Companies Table
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL,
    parent_id INT NULL,
    address TEXT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(120) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_type (type),
    INDEX idx_parent_id (parent_id),
    FOREIGN KEY (parent_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key to users table after companies table exists
ALTER TABLE users ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;

-- Locations Table
CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    company_id INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    address TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_company_id (company_id),
    INDEX idx_type (type),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Inventory Module Tables
-- ================================================================

-- Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Units of Measure Table
CREATE TABLE units_of_measure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tax Configurations Table
CREATE TABLE tax_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    rate DECIMAL(5,2) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Items Table
CREATE TABLE inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    category_id INT NULL,
    unit_of_measure_id INT NOT NULL,
    cost_price DECIMAL(15,4) DEFAULT 0.0000,
    selling_price DECIMAL(15,4) DEFAULT 0.0000,
    reorder_level INT DEFAULT 0,
    reorder_quantity INT DEFAULT 0,
    barcode VARCHAR(100) NULL,
    track_serial TINYINT(1) DEFAULT 0,
    track_lot TINYINT(1) DEFAULT 0,
    is_manufactured TINYINT(1) DEFAULT 0,
    tax_class_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_name (name),
    INDEX idx_category_id (category_id),
    INDEX idx_unit_of_measure_id (unit_of_measure_id),
    INDEX idx_barcode (barcode),
    INDEX idx_is_manufactured (is_manufactured),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_of_measure_id) REFERENCES units_of_measure(id) ON DELETE RESTRICT,
    FOREIGN KEY (tax_class_id) REFERENCES tax_configurations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Transactions Table
CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    location_id INT NOT NULL,
    transaction_type VARCHAR(20) NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(15,4) NULL,
    reference_type VARCHAR(20) NULL,
    reference_id INT NULL,
    serial_number VARCHAR(100) NULL,
    lot_number VARCHAR(100) NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item_id (item_id),
    INDEX idx_location_id (location_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_created_at (created_at),
    INDEX idx_created_by (created_by),
    INDEX idx_item_location (item_id, location_id),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Stock Levels Table
CREATE TABLE inventory_stock_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    location_id INT NOT NULL,
    quantity_on_hand INT DEFAULT 0,
    quantity_reserved INT DEFAULT 0,
    quantity_available INT DEFAULT 0,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_item_id (item_id),
    INDEX idx_location_id (location_id),
    UNIQUE INDEX idx_item_location (item_id, location_id),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ABC Classification Table
CREATE TABLE abc_classification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    classification VARCHAR(1) NOT NULL,
    annual_value DECIMAL(15,2) NOT NULL,
    percentage_of_total DECIMAL(5,2) NOT NULL,
    calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item_id (item_id),
    INDEX idx_classification (classification),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Finance Module Tables
-- ================================================================

-- Chart of Accounts Table
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    account_type VARCHAR(20) NOT NULL,
    parent_id INT NULL,
    level INT DEFAULT 1,
    is_main_account TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_account_type (account_type),
    INDEX idx_parent_id (parent_id),
    FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal Entries Table
CREATE TABLE journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_number VARCHAR(50) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    INDEX idx_entry_number (entry_number),
    INDEX idx_entry_date (entry_date),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal Entry Lines Table
CREATE TABLE journal_entry_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(15,2) DEFAULT 0.00,
    credit DECIMAL(15,2) DEFAULT 0.00,
    description TEXT NULL,
    INDEX idx_journal_entry_id (journal_entry_id),
    INDEX idx_account_id (account_id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- General Ledger Table
CREATE TABLE general_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    description TEXT NULL,
    debit DECIMAL(15,2) DEFAULT 0.00,
    credit DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_id (account_id),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_reference (reference_type, reference_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cash Accounts Table
CREATE TABLE cash_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    account_number VARCHAR(50) NULL,
    bank_name VARCHAR(100) NULL,
    current_balance DECIMAL(15,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_id (account_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Budget Table
CREATE TABLE budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    fiscal_year INT NOT NULL,
    period VARCHAR(20) NOT NULL,
    budgeted_amount DECIMAL(15,2) NOT NULL,
    actual_amount DECIMAL(15,2) DEFAULT 0.00,
    variance DECIMAL(15,2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_id (account_id),
    INDEX idx_fiscal_year (fiscal_year),
    INDEX idx_period (period),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Sales Module Tables
-- ================================================================

-- Customers Table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(120) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(100) NULL,
    credit_limit DECIMAL(15,2) DEFAULT 0.00,
    payment_terms VARCHAR(50) NULL,
    tax_exempt TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_code (customer_code),
    INDEX idx_name (name),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales Orders Table
CREATE TABLE sales_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    order_date DATE NOT NULL,
    required_date DATE NULL,
    shipped_date DATE NULL,
    status VARCHAR(20) DEFAULT 'draft',
    subtotal DECIMAL(15,2) DEFAULT 0.00,
    tax_amount DECIMAL(15,2) DEFAULT 0.00,
    shipping_cost DECIMAL(15,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    payment_status VARCHAR(20) DEFAULT 'unpaid',
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_number (order_number),
    INDEX idx_customer_id (customer_id),
    INDEX idx_order_date (order_date),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales Order Lines Table
CREATE TABLE sales_order_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,4) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL,
    notes TEXT NULL,
    INDEX idx_order_id (order_id),
    INDEX idx_item_id (item_id),
    FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quotes Table
CREATE TABLE quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    quote_date DATE NOT NULL,
    expiry_date DATE NULL,
    status VARCHAR(20) DEFAULT 'draft',
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_quote_number (quote_number),
    INDEX idx_customer_id (customer_id),
    INDEX idx_status (status),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coupons Table
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    discount_type VARCHAR(20) NOT NULL,
    discount_value DECIMAL(15,2) NOT NULL,
    min_purchase_amount DECIMAL(15,2) DEFAULT 0.00,
    max_discount_amount DECIMAL(15,2) NULL,
    valid_from DATE NULL,
    valid_to DATE NULL,
    usage_limit INT NULL,
    usage_count INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shipments Table
CREATE TABLE shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    shipment_number VARCHAR(50) NOT NULL UNIQUE,
    shipment_date DATE NOT NULL,
    carrier VARCHAR(100) NULL,
    tracking_number VARCHAR(100) NULL,
    status VARCHAR(20) DEFAULT 'pending',
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_shipment_number (shipment_number),
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Production Module Tables
-- ================================================================

-- Work Orders Table
CREATE TABLE work_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wo_number VARCHAR(50) NOT NULL UNIQUE,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    status VARCHAR(20) DEFAULT 'planned',
    priority VARCHAR(20) DEFAULT 'normal',
    location_id INT NOT NULL,
    start_date DATE NULL,
    due_date DATE NULL,
    completion_date DATE NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wo_number (wo_number),
    INDEX idx_product_id (product_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_location_id (location_id),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (product_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Work Order Progress Table
CREATE TABLE work_order_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT NOT NULL,
    status_from VARCHAR(20) NOT NULL,
    status_to VARCHAR(20) NOT NULL,
    notes TEXT NULL,
    updated_by INT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_work_order_id (work_order_id),
    INDEX idx_updated_by (updated_by),
    FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bill of Materials Table
CREATE TABLE bill_of_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    component_id INT NOT NULL,
    quantity_required DECIMAL(15,4) NOT NULL,
    unit_cost DECIMAL(15,4) NULL,
    version INT DEFAULT 1,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_id (product_id),
    INDEX idx_component_id (component_id),
    INDEX idx_version (version),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (product_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- MRP Module Tables
-- ================================================================

-- Master Production Schedule Table
CREATE TABLE master_production_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    planned_quantity INT NOT NULL,
    actual_quantity INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'planned',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_id (product_id),
    INDEX idx_period_start (period_start),
    INDEX idx_status (status),
    FOREIGN KEY (product_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MRP Runs Table
CREATE TABLE mrp_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_number VARCHAR(50) NOT NULL UNIQUE,
    run_date DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'in_progress',
    planned_orders_created INT DEFAULT 0,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_run_number (run_number),
    INDEX idx_run_date (run_date),
    INDEX idx_status (status),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Planned Orders Table
CREATE TABLE planned_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mrp_run_id INT NOT NULL,
    item_id INT NOT NULL,
    order_type VARCHAR(20) NOT NULL,
    quantity INT NOT NULL,
    planned_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'planned',
    converted_to_order_id INT NULL,
    notes TEXT NULL,
    INDEX idx_mrp_run_id (mrp_run_id),
    INDEX idx_item_id (item_id),
    INDEX idx_order_type (order_type),
    INDEX idx_status (status),
    FOREIGN KEY (mrp_run_id) REFERENCES mrp_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Analytics Module Tables  
-- ================================================================

-- Sales Analytics Table
CREATE TABLE sales_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_type VARCHAR(20) NOT NULL,
    period_date DATE NOT NULL,
    total_sales DECIMAL(15,2) DEFAULT 0.00,
    total_orders INT DEFAULT 0,
    average_order_value DECIMAL(15,2) DEFAULT 0.00,
    top_selling_item_id INT NULL,
    calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_period_type (period_type),
    INDEX idx_period_date (period_date),
    FOREIGN KEY (top_selling_item_id) REFERENCES inventory_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Analytics Table
CREATE TABLE inventory_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_date DATE NOT NULL,
    total_value DECIMAL(15,2) DEFAULT 0.00,
    total_items INT DEFAULT 0,
    low_stock_items INT DEFAULT 0,
    out_of_stock_items INT DEFAULT 0,
    turnover_ratio DECIMAL(10,2) DEFAULT 0.00,
    calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_period_date (period_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

-- ================================================================
-- Initial Data Inserts
-- ================================================================

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, role, active) 
VALUES ('admin', 'admin@mjrgroup.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Insert MJR Group parent company
INSERT INTO companies (code, name, type, is_active, address, phone, email)
VALUES ('MJR', 'MJR Group of Companies', 'parent', 1, 'MJR Group Head Office', '+1234567890', 'info@mjrgroup.com');

-- Insert subsidiaries
INSERT INTO companies (code, name, type, parent_id, is_active, address, phone, email)
VALUES 
('MWTC', 'MJR Water Tank Company', 'subsidiary', 1, 1, 'MJR Water Tank Company Address', '+1234567890', 'info@mwtc.mjrgroup.com'),
('MAD', 'MJR Auto Dealer', 'subsidiary', 1, 1, 'MJR Auto Dealer Address', '+1234567890', 'info@mad.mjrgroup.com'),
('ML', 'MJR Lubricant', 'subsidiary', 1, 1, 'MJR Lubricant Address', '+1234567890', 'info@ml.mjrgroup.com'),
('MJRHOFOA', 'MJR Company (Hofoa Gas Station)', 'subsidiary', 1, 1, 'MJR Company (Hofoa Gas Station) Address', '+1234567890', 'info@mjrhofoa.mjrgroup.com');

-- Insert default units of measure
INSERT INTO units_of_measure (code, name, is_active)
VALUES 
('PCS', 'Pieces', 1),
('KG', 'Kilograms', 1),
('LTR', 'Liters', 1),
('MTR', 'Meters', 1),
('BOX', 'Box', 1);

-- Insert default categories
INSERT INTO categories (name, description, is_active)
VALUES 
('Raw Materials', 'Materials used in production', 1),
('Finished Goods', 'Completed products ready for sale', 1),
('Office Supplies', 'Office and administrative supplies', 1),
('Equipment', 'Tools and equipment', 1);

-- Insert default chart of accounts
INSERT INTO accounts (code, name, account_type, level, is_main_account, is_active)
VALUES
-- Assets
('1000', 'Assets', 'asset', 0, 1, 1),
('1100', 'Current Assets', 'asset', 1, 1, 1),
('1110', 'Cash', 'asset', 2, 0, 1),
('1120', 'Accounts Receivable', 'asset', 2, 0, 1),
('1130', 'Inventory', 'asset', 2, 0, 1),
-- Liabilities
('2000', 'Liabilities', 'liability', 0, 1, 1),
('2100', 'Current Liabilities', 'liability', 1, 1, 1),
('2110', 'Accounts Payable', 'liability', 2, 0, 1),
('2120', 'Accrued Expenses', 'liability', 2, 0, 1),
-- Equity
('3000', 'Equity', 'equity', 0, 1, 1),
('3100', 'Owner Equity', 'equity', 1, 0, 1),
('3200', 'Retained Earnings', 'equity', 1, 0, 1),
-- Revenue
('4000', 'Revenue', 'revenue', 0, 1, 1),
('4100', 'Sales Revenue', 'revenue', 1, 0, 1),
-- Expenses
('5000', 'Cost of Goods Sold', 'expense', 0, 1, 1),
('6000', 'Operating Expenses', 'expense', 0, 1, 1),
('6100', 'Salaries and Wages', 'expense', 1, 0, 1),
('6200', 'Rent', 'expense', 1, 0, 1),
('6300', 'Utilities', 'expense', 1, 0, 1);

-- ================================================================
-- End of Schema
-- ================================================================
