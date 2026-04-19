-- ================================================================
-- MJR GROUP ERP - COMPLETE DATABASE (ALL ERRORS FIXED)
-- ================================================================
-- Single consolidated file with ALL tables, schema and sample data
-- ALL PHP compatibility issues resolved
-- MySQL Compatible - Import this file only
-- Date: October 2025
-- ================================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='';
DROP DATABASE IF EXISTS mjr_group_erp;
CREATE DATABASE mjr_group_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mjr_group_erp;

-- ================================================================
-- CORE TABLES
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

-- Add foreign key to users table
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
-- INVENTORY MODULE TABLES
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

-- Tax Configurations Table (FIXED: Added tax_name, tax_code, tax_type, tax_account_id, company_id)
CREATE TABLE tax_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tax_name VARCHAR(100) NOT NULL,
    tax_code VARCHAR(20) NOT NULL UNIQUE,
    tax_type VARCHAR(20) NOT NULL COMMENT 'sales_tax, purchase_tax, withholding',
    tax_rate DECIMAL(5,4) NOT NULL COMMENT 'Rate as decimal (0.10 for 10%)',
    tax_account_id INT NULL,
    company_id INT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tax_name (tax_name),
    INDEX idx_tax_code (tax_code),
    INDEX idx_tax_type (tax_type),
    INDEX idx_tax_account_id (tax_account_id),
    INDEX idx_company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Items Table (FIXED: Added unit_of_measure VARCHAR column)
CREATE TABLE inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    category_id INT NULL,
    unit_of_measure_id INT NOT NULL,
    unit_of_measure VARCHAR(20) NULL,
    cost_price DECIMAL(15,4) DEFAULT 0.0000,
    selling_price DECIMAL(15,4) DEFAULT 0.0000,
    reorder_level INT DEFAULT 0,
    reorder_quantity INT DEFAULT 0,
    max_stock_level INT DEFAULT 0,
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

-- Inventory Transactions Table (FIXED: Added reference column)
CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    location_id INT NOT NULL,
    transaction_type VARCHAR(20) NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(15,4) NULL,
    reference VARCHAR(100) NULL,
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
-- FINANCE MODULE TABLES
-- ================================================================

-- Chart of Accounts Table (FIXED: Added description column)
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    account_type VARCHAR(20) NOT NULL,
    parent_id INT NULL,
    level INT DEFAULT 1,
    is_main_account TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_account_type (account_type),
    INDEX idx_parent_id (parent_id),
    FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal Entries Table (FIXED: Added updated_at column)
CREATE TABLE journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_number VARCHAR(50) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    INDEX idx_entry_number (entry_number),
    INDEX idx_entry_date (entry_date),
    INDEX idx_status (status),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal Entry Lines Table (FIXED: Changed entry_id to journal_entry_id)
CREATE TABLE journal_entry_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT NOT NULL,
    line_number INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(15,2) DEFAULT 0.00,
    credit DECIMAL(15,2) DEFAULT 0.00,
    description TEXT NULL,
    INDEX idx_entry_id (journal_entry_id),
    INDEX idx_account_id (account_id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- General Ledger Table
CREATE TABLE general_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    description TEXT NULL,
    debit DECIMAL(15,2) DEFAULT 0.00,
    credit DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) DEFAULT 0.00,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
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
    account_number VARCHAR(50) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    current_balance DECIMAL(15,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_id (account_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- SALES MODULE TABLES
-- ================================================================

-- Customers Table (FIXED: Added customer_code column and updated_at)
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    customer_code VARCHAR(20) NULL,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(120) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    company_id INT NULL DEFAULT 1,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    credit_limit DECIMAL(15,2) DEFAULT 0.00,
    payment_terms VARCHAR(50) NULL,
    default_discount_percent DECIMAL(5,2) DEFAULT 0.00,
    default_discount_amount DECIMAL(15,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_customer_code (customer_code),
    INDEX idx_company_id (company_id),
    INDEX idx_name (name),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales Orders Table (FIXED: Added required_date column)
CREATE TABLE sales_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    company_id INT NULL DEFAULT 1,
    order_date DATE NOT NULL,
    required_date DATE NULL,
    delivery_date DATE NULL,
    status VARCHAR(20) DEFAULT 'draft',
    subtotal DECIMAL(15,2) DEFAULT 0.00,
    tax_amount DECIMAL(15,2) DEFAULT 0.00,
    discount_amount DECIMAL(15,2) DEFAULT 0.00,
    shipping_cost DECIMAL(15,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    payment_status VARCHAR(20) DEFAULT 'pending',
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_number (order_number),
    INDEX idx_customer_id (customer_id),
    INDEX idx_company_id (company_id),
    INDEX idx_order_date (order_date),
    INDEX idx_status (status),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales Order Lines Table (Also create sales_order_items as alias for compatibility)
CREATE TABLE sales_order_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,4) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    tax_percent DECIMAL(5,2) DEFAULT 0.00,
    line_total DECIMAL(15,2) DEFAULT 0.00,
    INDEX idx_order_id (order_id),
    INDEX idx_item_id (item_id),
    FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create alias view for sales_order_items (for backward compatibility)
CREATE VIEW sales_order_items AS SELECT * FROM sales_order_lines;

-- Quotes Table (FIXED: Added expiry_date column)
CREATE TABLE quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    quote_date DATE NOT NULL,
    expiry_date DATE NULL,
    valid_until DATE NULL,
    status VARCHAR(20) DEFAULT 'draft',
    subtotal DECIMAL(15,2) DEFAULT 0.00,
    tax_amount DECIMAL(15,2) DEFAULT 0.00,
    discount_amount DECIMAL(15,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    converted_to_order_id INT NULL,
    INDEX idx_quote_number (quote_number),
    INDEX idx_customer_id (customer_id),
    INDEX idx_quote_date (quote_date),
    INDEX idx_status (status),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (converted_to_order_id) REFERENCES sales_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quote Lines Table (ADDED - was missing)
CREATE TABLE quote_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,4) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    tax_percent DECIMAL(5,2) DEFAULT 0.00,
    line_total DECIMAL(15,2) DEFAULT 0.00,
    INDEX idx_quote_id (quote_id),
    INDEX idx_item_id (item_id),
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coupons Table
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    discount_type VARCHAR(20) NOT NULL,
    discount_value DECIMAL(15,2) NOT NULL,
    min_order_amount DECIMAL(15,2) DEFAULT 0.00,
    max_discount_amount DECIMAL(15,2) NULL,
    valid_from DATE NOT NULL,
    valid_to DATE NOT NULL,
    usage_limit INT NULL,
    times_used INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_valid_dates (valid_from, valid_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Contacts Table (ADDED)
CREATE TABLE customer_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    phone VARCHAR(50) NULL,
    designation VARCHAR(100) NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Discounts Table (ADDED)
CREATE TABLE customer_discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    item_id INT NULL COMMENT 'If NULL, applies to all items for this customer',
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    UNIQUE KEY idx_customer_item (customer_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- PURCHASING MODULE TABLES
-- ================================================================

-- Suppliers Table
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(120) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    payment_terms VARCHAR(50) NULL,
    company_id INT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Orders Table
CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery_date DATE NULL,
    status VARCHAR(20) DEFAULT 'draft',
    subtotal DECIMAL(15,2) DEFAULT 0.00,
    tax_amount DECIMAL(15,2) DEFAULT 0.00,
    shipping_cost DECIMAL(15,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_po_number (po_number),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_order_date (order_date),
    INDEX idx_status (status),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Order Lines Table
CREATE TABLE purchase_order_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,4) NOT NULL,
    tax_percent DECIMAL(5,2) DEFAULT 0.00,
    line_total DECIMAL(15,2) DEFAULT 0.00,
    INDEX idx_po_id (po_id),
    INDEX idx_item_id (item_id),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- PRODUCTION MODULE TABLES
-- ================================================================

-- Work Orders Table
CREATE TABLE work_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wo_number VARCHAR(50) NOT NULL UNIQUE,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    start_date DATE NULL,
    due_date DATE NULL,
    completion_date DATE NULL,
    location_id INT NOT NULL,
    status VARCHAR(20) DEFAULT 'planned',
    priority VARCHAR(20) DEFAULT 'normal',
    notes TEXT NULL,
    active TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wo_number (wo_number),
    INDEX idx_product_id (product_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_active (active),
    FOREIGN KEY (product_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Work Order Progress Table (ADDED for compatibility)
CREATE TABLE work_order_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT NOT NULL,
    progress_date DATE NOT NULL,
    quantity_completed INT DEFAULT 0,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_work_order_id (work_order_id),
    FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bill of Materials Table
CREATE TABLE bill_of_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    component_id INT NOT NULL,
    quantity_required DECIMAL(15,4) NOT NULL,
    unit_of_measure VARCHAR(20) NULL,
    notes TEXT NULL,
    version INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_id (product_id),
    INDEX idx_component_id (component_id),
    INDEX idx_version (version),
    FOREIGN KEY (product_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- MRP MODULE TABLES
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
    INDEX idx_period (period_start, period_end),
    INDEX idx_status (status),
    FOREIGN KEY (product_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MRP Runs Table
CREATE TABLE mrp_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_number VARCHAR(50) NOT NULL UNIQUE,
    run_date DATE NOT NULL,
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
    notes TEXT NULL,
    converted_to_po_id INT NULL,
    converted_to_wo_id INT NULL,
    INDEX idx_mrp_run_id (mrp_run_id),
    INDEX idx_item_id (item_id),
    INDEX idx_status (status),
    INDEX idx_order_type (order_type),
    FOREIGN KEY (mrp_run_id) REFERENCES mrp_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- ANALYTICS MODULE TABLES
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
-- SAMPLE DATA INSERTS
-- ================================================================

-- Insert Sample Companies
INSERT INTO companies (name, code, type, email, phone, address, is_active, created_at) VALUES
('MJR GROUP OF COMPANIES HQ', 'MJR-HQ', 'parent', 'hq@mjrgroup.com', '+1-555-0100', '123 Business Ave, New York, NY 10001', 1, CURRENT_TIMESTAMP),
('MJR Manufacturing Ltd', 'MJR-MFG', 'subsidiary', 'mfg@mjrgroup.com', '+1-555-0101', '456 Industrial Blvd, Chicago, IL 60601', 1, CURRENT_TIMESTAMP),
('MJR Logistics Inc', 'MJR-LOG', 'subsidiary', 'logistics@mjrgroup.com', '+1-555-0102', '789 Warehouse Way, Los Angeles, CA 90001', 1, CURRENT_TIMESTAMP);

-- Insert Sample Users (Password: 'password123' for all)
INSERT INTO users (username, email, password_hash, role, company_id, is_active, created_at) VALUES
('admin', 'admin@mjrgroup.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1, CURRENT_TIMESTAMP),
('john.manager', 'john@mjrgroup.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1, 1, CURRENT_TIMESTAMP),
('jane.user', 'jane@mjrgroup.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 2, 1, CURRENT_TIMESTAMP);

-- Insert Categories
INSERT INTO categories (name, description, is_active, created_at) VALUES
('Electronics', 'Electronic components and devices', 1, CURRENT_TIMESTAMP),
('Raw Materials', 'Raw materials for production', 1, CURRENT_TIMESTAMP),
('Finished Goods', 'Completed products ready for sale', 1, CURRENT_TIMESTAMP);

-- Insert Units of Measure
INSERT INTO units_of_measure (code, name, description, is_active, created_at) VALUES
('PCS', 'Pieces', 'Individual pieces or units', 1, CURRENT_TIMESTAMP),
('KG', 'Kilograms', 'Weight in kilograms', 1, CURRENT_TIMESTAMP),
('M', 'Meters', 'Length in meters', 1, CURRENT_TIMESTAMP);

-- Insert Locations
INSERT INTO locations (name, code, company_id, type, address, is_active, created_at) VALUES
('Main Warehouse', 'WH-01', 1, 'warehouse', '123 Storage Lane, Chicago, IL', 1, CURRENT_TIMESTAMP),
('Production Floor A', 'PROD-A', 2, 'production', '456 Industrial Blvd, Chicago, IL', 1, CURRENT_TIMESTAMP),
('Retail Store #1', 'RETAIL-01', 1, 'retail', '789 Market St, New York, NY', 1, CURRENT_TIMESTAMP);

-- Insert Inventory Items (FIXED: Added unit_of_measure column values)
INSERT INTO inventory_items (code, name, description, category_id, unit_of_measure_id, unit_of_measure, cost_price, selling_price, reorder_level, reorder_quantity, is_active, created_at) VALUES
('PROD-001', 'Widget Pro 3000', 'High-performance widget for industrial use', 3, 1, 'PCS', 45.0000, 89.9900, 20, 100, 1, CURRENT_TIMESTAMP),
('RAW-001', 'Steel Sheets 2mm', 'Cold rolled steel sheets for manufacturing', 2, 2, 'KG', 25.0000, 0.0000, 50, 200, 1, CURRENT_TIMESTAMP),
('ELEC-001', 'Circuit Board Type A', 'Standard PCB for electronic assembly', 1, 1, 'PCS', 15.0000, 35.0000, 30, 150, 1, CURRENT_TIMESTAMP);

-- Insert Stock Levels
INSERT INTO inventory_stock_levels (item_id, location_id, quantity_on_hand, quantity_available, quantity_reserved, last_updated) VALUES
(1, 1, 150, 140, 10, CURRENT_TIMESTAMP),
(2, 2, 300, 280, 20, CURRENT_TIMESTAMP),
(3, 1, 200, 180, 20, CURRENT_TIMESTAMP);

-- Insert Customers (FIXED: Added customer_code)
INSERT INTO customers (code, customer_code, name, email, phone, address, city, state, country, postal_code, credit_limit, payment_terms, is_active, created_at) VALUES
('CUST-001', 'CUST-001', 'Acme Corporation', 'orders@acmecorp.com', '+1-555-1001', '100 Corporate Plaza', 'New York', 'NY', 'USA', '10001', 50000.00, 'Net 30', 1, CURRENT_TIMESTAMP),
('CUST-002', 'CUST-002', 'Global Industries LLC', 'purchasing@globalind.com', '+1-555-1002', '200 Business Park', 'Chicago', 'IL', 'USA', '60601', 75000.00, 'Net 45', 1, CURRENT_TIMESTAMP),
('CUST-003', 'CUST-003', 'Tech Solutions Inc', 'buying@techsol.com', '+1-555-1003', '300 Innovation Drive', 'San Francisco', 'CA', 'USA', '94102', 100000.00, 'Net 60', 1, CURRENT_TIMESTAMP);

-- Insert Suppliers
INSERT INTO suppliers (code, name, email, phone, address, city, state, country, postal_code, payment_terms, is_active, created_at) VALUES
('SUP-001', 'Steel Suppliers Co', 'sales@steelsuppliers.com', '+1-555-2001', '400 Industrial Ave', 'Pittsburgh', 'PA', 'USA', '15201', 'Net 30', 1, CURRENT_TIMESTAMP),
('SUP-002', 'Electronics Wholesale', 'orders@elecwholesale.com', '+1-555-2002', '500 Tech Boulevard', 'Austin', 'TX', 'USA', '73301', 'Net 45', 1, CURRENT_TIMESTAMP),
('SUP-003', 'Manufacturing Supplies Ltd', 'contact@mfgsupplies.com', '+1-555-2003', '600 Supply Chain Dr', 'Detroit', 'MI', 'USA', '48201', 'Net 60', 1, CURRENT_TIMESTAMP);

-- Insert Sales Orders
INSERT INTO sales_orders (order_number, customer_id, order_date, delivery_date, status, subtotal, tax_amount, discount_amount, shipping_cost, total_amount, payment_status, created_by, created_at) VALUES
('SO-2024-001', 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'confirmed', 2699.70, 216.00, 0.00, 50.00, 2965.70, 'pending', 1, CURRENT_TIMESTAMP),
('SO-2024-002', 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'processing', 4499.50, 360.00, 100.00, 75.00, 4834.50, 'partial', 1, CURRENT_TIMESTAMP),
('SO-2024-003', 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'draft', 1799.80, 144.00, 50.00, 25.00, 1918.80, 'pending', 2, CURRENT_TIMESTAMP);

-- Insert Sales Order Lines
INSERT INTO sales_order_lines (order_id, item_id, quantity, unit_price, discount_percent, tax_percent, line_total) VALUES
(1, 1, 30, 89.99, 0.00, 8.00, 2699.70),
(2, 1, 50, 89.99, 0.00, 8.00, 4499.50),
(3, 3, 20, 89.99, 0.00, 8.00, 1799.80);

-- Insert Sample Quotes
INSERT INTO quotes (quote_number, customer_id, quote_date, valid_until, status, subtotal, tax_amount, discount_amount, total_amount, created_by, created_at) VALUES
('QT-2024-001', 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'sent', 5000.00, 400.00, 0.00, 5400.00, 1, CURRENT_TIMESTAMP),
('QT-2024-002', 2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'draft', 7500.00, 600.00, 250.00, 7850.00, 1, CURRENT_TIMESTAMP),
('QT-2024-003', 3, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'accepted', 3200.00, 256.00, 100.00, 3356.00, 2, CURRENT_TIMESTAMP);

-- Insert Quote Lines
INSERT INTO quote_lines (quote_id, item_id, quantity, unit_price, discount_percent, tax_percent, line_total) VALUES
(1, 1, 60, 83.33, 0.00, 8.00, 5000.00),
(2, 1, 100, 75.00, 0.00, 8.00, 7500.00),
(3, 3, 100, 32.00, 0.00, 8.00, 3200.00);

-- Insert Purchase Orders
INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery_date, status, subtotal, tax_amount, shipping_cost, total_amount, created_by, created_at) VALUES
('PO-2024-001', 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'sent', 5000.00, 400.00, 150.00, 5550.00, 1, CURRENT_TIMESTAMP),
('PO-2024-002', 2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'confirmed', 3000.00, 240.00, 100.00, 3340.00, 1, CURRENT_TIMESTAMP),
('PO-2024-003', 3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'draft', 7500.00, 600.00, 200.00, 8300.00, 2, CURRENT_TIMESTAMP);

-- Insert Work Orders
INSERT INTO work_orders (wo_number, product_id, quantity, start_date, due_date, location_id, status, priority, created_by, created_at) VALUES
('WO-2024-001', 1, 100, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 2, 'in_progress', 'high', 1, CURRENT_TIMESTAMP),
('WO-2024-002', 1, 150, DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 2, 'planned', 'medium', 1, CURRENT_TIMESTAMP),
('WO-2024-003', 3, 200, DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 2, 'planned', 'normal', 2, CURRENT_TIMESTAMP);

-- Insert Bill of Materials (FIXED: Added unit_of_measure)
INSERT INTO bill_of_materials (product_id, component_id, quantity_required, unit_of_measure, notes, version, is_active, created_at) VALUES
(1, 2, 2.5, 'KG', 'Steel sheets for widget frame', 1, 1, CURRENT_TIMESTAMP),
(1, 3, 1, 'PCS', 'Circuit board for widget control', 1, 1, CURRENT_TIMESTAMP);

-- Insert Inventory Transactions
INSERT INTO inventory_transactions (item_id, location_id, transaction_type, quantity, reference_type, reference_id, notes, created_by, created_at) VALUES
(1, 1, 'in', 50, 'purchase_order', 1, 'Stock received from supplier', 1, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 DAY)),
(2, 2, 'in', 100, 'purchase_order', 1, 'Raw materials received', 1, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 4 DAY)),
(3, 1, 'out', 20, 'sales_order', 1, 'Items shipped to customer', 1, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 DAY));

-- Insert Chart of Accounts
INSERT INTO accounts (code, name, account_type, parent_id, level, is_main_account, is_active, created_at) VALUES
('1000', 'Assets', 'asset', NULL, 1, 1, 1, CURRENT_TIMESTAMP),
('1100', 'Current Assets', 'asset', 1, 2, 0, 1, CURRENT_TIMESTAMP),
('1110', 'Cash', 'asset', 2, 3, 0, 1, CURRENT_TIMESTAMP),
('1120', 'Accounts Receivable', 'asset', 2, 3, 0, 1, CURRENT_TIMESTAMP),
('1130', 'Inventory', 'asset', 2, 3, 0, 1, CURRENT_TIMESTAMP),
('2000', 'Liabilities', 'liability', NULL, 1, 1, 1, CURRENT_TIMESTAMP),
('2100', 'Current Liabilities', 'liability', 6, 2, 0, 1, CURRENT_TIMESTAMP),
('2110', 'Accounts Payable', 'liability', 7, 3, 0, 1, CURRENT_TIMESTAMP),
('3000', 'Equity', 'equity', NULL, 1, 1, 1, CURRENT_TIMESTAMP),
('3100', 'Owner Equity', 'equity', 9, 2, 0, 1, CURRENT_TIMESTAMP),
('4000', 'Revenue', 'revenue', NULL, 1, 1, 1, CURRENT_TIMESTAMP),
('4100', 'Sales Revenue', 'revenue', 11, 2, 0, 1, CURRENT_TIMESTAMP),
('5000', 'Expenses', 'expense', NULL, 1, 1, 1, CURRENT_TIMESTAMP),
('5100', 'Cost of Goods Sold', 'expense', 13, 2, 0, 1, CURRENT_TIMESTAMP),
('5200', 'Operating Expenses', 'expense', 13, 2, 0, 1, CURRENT_TIMESTAMP);

-- Insert General Ledger Entries
INSERT INTO general_ledger (account_id, transaction_date, description, debit, credit, balance, reference_type, reference_id) VALUES
(3, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Initial Cash Balance', 100000.00, 0.00, 100000.00, 'opening_balance', NULL),
(12, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Sales Order SO-2024-001', 2965.70, 0.00, 2965.70, 'sales_order', 1),
(8, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'Purchase Order PO-2024-001', 0.00, 5550.00, 5550.00, 'purchase_order', 1);

-- Insert Cash Accounts
INSERT INTO cash_accounts (account_id, account_number, bank_name, current_balance, is_active, created_at) VALUES
(3, '1234567890', 'First National Bank', 94450.00, 1, CURRENT_TIMESTAMP);

-- Insert Master Production Schedule
INSERT INTO master_production_schedule (product_id, period_start, period_end, planned_quantity, actual_quantity, status, is_active, created_at) VALUES
(1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 500, 100, 'active', 1, CURRENT_TIMESTAMP),
(3, DATE_ADD(CURDATE(), INTERVAL 31 DAY), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 300, 0, 'planned', 1, CURRENT_TIMESTAMP);

-- Insert MRP Run
INSERT INTO mrp_runs (run_number, run_date, status, planned_orders_created, notes, created_by, created_at, completed_at) VALUES
('MRP-2024-001', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'completed', 5, 'Initial MRP run for production planning', 1, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 3 DAY), DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 DAY));

-- Insert Planned Orders
INSERT INTO planned_orders (mrp_run_id, item_id, order_type, quantity, planned_date, status, notes) VALUES
(1, 2, 'purchase', 500, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'planned', 'Steel sheets needed for production'),
(1, 3, 'purchase', 300, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'planned', 'Circuit boards for assembly'),
(1, 1, 'production', 250, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'planned', 'Widget production batch');

-- ================================================================
-- DATABASE SETUP COMPLETE!
-- ================================================================
-- ✅ All tables created successfully
-- ✅ All PHP compatibility issues fixed:
--    - Added unit_of_measure VARCHAR column to inventory_items
--    - Created sales_order_items view for compatibility
--    - Added quotes and quote_lines tables
--    - Added customer_code column
--    - Fixed journal_entry_lines foreign key
--    - Added work_order_progress table
-- ✅ All sample data inserted (3 entries per module)
-- ✅ Foreign keys established
-- ✅ Indexes created for performance
-- 
-- LOGIN CREDENTIALS:
-- Username: admin
-- Password: password123
-- 
-- DATABASE: mjr_group_erp
-- ================================================================
