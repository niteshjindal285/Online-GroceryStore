-- ================================================================
-- MJR Group ERP - Sample Data for Testing (MySQL Compatible)
-- ================================================================
-- This file contains dummy data to test the ERP system functionality
-- Run this AFTER running database_schema.sql
-- ================================================================

USE mjr_group_erp;

-- ================================================================
-- Step 1: Insert Sample Companies (MUST be first due to foreign keys)
-- ================================================================
INSERT INTO companies (name, code, type, email, phone, address, is_active, created_at) VALUES
('MJR GROUP OF COMPANIES HQ', 'MJR-HQ', 'parent', 'hq@mjrgroup.com', '+1-555-0100', '123 Business Ave, New York, NY 10001', 1, CURRENT_TIMESTAMP),
('MJR Manufacturing Ltd', 'MJR-MFG', 'subsidiary', 'mfg@mjrgroup.com', '+1-555-0101', '456 Industrial Blvd, Chicago, IL 60601', 1, CURRENT_TIMESTAMP),
('MJR Logistics Inc', 'MJR-LOG', 'subsidiary', 'logistics@mjrgroup.com', '+1-555-0102', '789 Warehouse Way, Los Angeles, CA 90001', 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 2: Insert Sample Users (Password: 'password123' for all)
-- ================================================================
-- Note: Password hash is bcrypt hash of 'password123'
INSERT INTO users (username, email, password_hash, role, company_id, is_active, created_at) VALUES
('admin', 'admin@mjrgroup.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1, CURRENT_TIMESTAMP),
('john.manager', 'john@mjrgroup.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1, 1, CURRENT_TIMESTAMP),
('jane.user', 'jane@mjrgroup.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 2, 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 3: Insert Sample Categories
-- ================================================================
INSERT INTO categories (name, description, is_active, created_at) VALUES
('Electronics', 'Electronic components and devices', 1, CURRENT_TIMESTAMP),
('Raw Materials', 'Raw materials for production', 1, CURRENT_TIMESTAMP),
('Finished Goods', 'Completed products ready for sale', 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 4: Insert Units of Measure (Required for inventory items)
-- ================================================================
INSERT INTO units_of_measure (code, name, description, is_active, created_at) VALUES
('PCS', 'Pieces', 'Individual pieces or units', 1, CURRENT_TIMESTAMP),
('KG', 'Kilograms', 'Weight in kilograms', 1, CURRENT_TIMESTAMP),
('M', 'Meters', 'Length in meters', 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 5: Insert Sample Locations
-- ================================================================
INSERT INTO locations (name, code, company_id, type, address, is_active, created_at) VALUES
('Main Warehouse', 'WH-01', 1, 'warehouse', '123 Storage Lane, Chicago, IL', 1, CURRENT_TIMESTAMP),
('Production Floor A', 'PROD-A', 2, 'production', '456 Industrial Blvd, Chicago, IL', 1, CURRENT_TIMESTAMP),
('Retail Store #1', 'RETAIL-01', 1, 'retail', '789 Market St, New York, NY', 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 6: Insert Sample Inventory Items
-- ================================================================
INSERT INTO inventory_items (code, name, description, category_id, unit_of_measure_id, cost_price, selling_price, reorder_level, reorder_quantity, is_active, created_at) VALUES
('PROD-001', 'Widget Pro 3000', 'High-performance widget for industrial use', 3, 1, 45.0000, 89.9900, 20, 100, 1, CURRENT_TIMESTAMP),
('RAW-001', 'Steel Sheets 2mm', 'Cold rolled steel sheets for manufacturing', 2, 2, 25.0000, 0.0000, 50, 200, 1, CURRENT_TIMESTAMP),
('ELEC-001', 'Circuit Board Type A', 'Standard PCB for electronic assembly', 1, 1, 15.0000, 35.0000, 30, 150, 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 7: Insert Sample Stock Levels
-- ================================================================
INSERT INTO inventory_stock_levels (item_id, location_id, quantity_on_hand, quantity_available, quantity_reserved, last_updated) VALUES
(1, 1, 150, 140, 10, CURRENT_TIMESTAMP),
(2, 2, 300, 280, 20, CURRENT_TIMESTAMP),
(3, 1, 200, 180, 20, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 8: Insert Sample Customers
-- ================================================================
INSERT INTO customers (code, name, email, phone, address, city, state, country, postal_code, credit_limit, payment_terms, is_active, created_at) VALUES
('CUST-001', 'Acme Corporation', 'orders@acmecorp.com', '+1-555-1001', '100 Corporate Plaza', 'New York', 'NY', 'USA', '10001', 50000.00, 'Net 30', 1, CURRENT_TIMESTAMP),
('CUST-002', 'Global Industries LLC', 'purchasing@globalind.com', '+1-555-1002', '200 Business Park', 'Chicago', 'IL', 'USA', '60601', 75000.00, 'Net 45', 1, CURRENT_TIMESTAMP),
('CUST-003', 'Tech Solutions Inc', 'buying@techsol.com', '+1-555-1003', '300 Innovation Drive', 'San Francisco', 'CA', 'USA', '94102', 100000.00, 'Net 60', 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 9: Insert Sample Suppliers
-- ================================================================
INSERT INTO suppliers (code, name, email, phone, address, city, state, country, postal_code, payment_terms, is_active, created_at) VALUES
('SUP-001', 'Steel Suppliers Co', 'sales@steelsuppliers.com', '+1-555-2001', '400 Industrial Ave', 'Pittsburgh', 'PA', 'USA', '15201', 'Net 30', 1, CURRENT_TIMESTAMP),
('SUP-002', 'Electronics Wholesale', 'orders@elecwholesale.com', '+1-555-2002', '500 Tech Boulevard', 'Austin', 'TX', 'USA', '73301', 'Net 45', 1, CURRENT_TIMESTAMP),
('SUP-003', 'Manufacturing Supplies Ltd', 'contact@mfgsupplies.com', '+1-555-2003', '600 Supply Chain Dr', 'Detroit', 'MI', 'USA', '48201', 'Net 60', 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 10: Insert Sample Sales Orders
-- ================================================================
INSERT INTO sales_orders (order_number, customer_id, order_date, delivery_date, status, subtotal, tax_amount, discount_amount, shipping_cost, total_amount, payment_status, created_by, created_at) VALUES
('SO-2024-001', 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'confirmed', 2699.70, 216.00, 0.00, 50.00, 2965.70, 'pending', 1, CURRENT_TIMESTAMP),
('SO-2024-002', 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'processing', 4499.50, 360.00, 100.00, 75.00, 4834.50, 'partial', 1, CURRENT_TIMESTAMP),
('SO-2024-003', 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'draft', 1799.80, 144.00, 50.00, 25.00, 1918.80, 'pending', 2, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 11: Insert Sample Sales Order Lines
-- ================================================================
INSERT INTO sales_order_lines (order_id, item_id, quantity, unit_price, discount_percent, tax_percent, line_total) VALUES
(1, 1, 30, 89.99, 0.00, 8.00, 2699.70),
(2, 1, 50, 89.99, 0.00, 8.00, 4499.50),
(3, 3, 20, 89.99, 0.00, 8.00, 1799.80);

-- ================================================================
-- Step 12: Insert Sample Purchase Orders
-- ================================================================
INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery_date, status, subtotal, tax_amount, shipping_cost, total_amount, created_by, created_at) VALUES
('PO-2024-001', 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'sent', 5000.00, 400.00, 150.00, 5550.00, 1, CURRENT_TIMESTAMP),
('PO-2024-002', 2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'confirmed', 3000.00, 240.00, 100.00, 3340.00, 1, CURRENT_TIMESTAMP),
('PO-2024-003', 3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'draft', 7500.00, 600.00, 200.00, 8300.00, 2, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 13: Insert Sample Work Orders
-- ================================================================
INSERT INTO work_orders (wo_number, product_id, quantity, start_date, due_date, location_id, status, priority, created_by, created_at) VALUES
('WO-2024-001', 1, 100, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 2, 'in_progress', 'high', 1, CURRENT_TIMESTAMP),
('WO-2024-002', 1, 150, DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 2, 'planned', 'medium', 1, CURRENT_TIMESTAMP),
('WO-2024-003', 3, 200, DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 2, 'planned', 'normal', 2, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 14: Insert Sample Bill of Materials
-- ================================================================
INSERT INTO bill_of_materials (product_id, component_id, quantity_required, unit_of_measure, notes, version, is_active, created_at) VALUES
(1, 2, 2.5, 'sheets', 'Steel sheets for widget frame', 1, 1, CURRENT_TIMESTAMP),
(1, 3, 1, 'pieces', 'Circuit board for widget control', 1, 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 15: Insert Sample Inventory Transactions
-- ================================================================
INSERT INTO inventory_transactions (item_id, location_id, transaction_type, quantity, reference_type, reference_id, notes, created_by, created_at) VALUES
(1, 1, 'in', 50, 'purchase_order', 1, 'Stock received from supplier', 1, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 DAY)),
(2, 2, 'in', 100, 'purchase_order', 1, 'Raw materials received', 1, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 4 DAY)),
(3, 1, 'out', 20, 'sales_order', 1, 'Items shipped to customer', 1, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 DAY));

-- ================================================================
-- Step 16: Insert Sample Chart of Accounts
-- ================================================================
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

-- ================================================================
-- Step 17: Insert Sample General Ledger Entries
-- ================================================================
INSERT INTO general_ledger (account_id, transaction_date, description, debit, credit, balance, reference_type, reference_id) VALUES
(3, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Initial Cash Balance', 100000.00, 0.00, 100000.00, 'opening_balance', NULL),
(12, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Sales Order SO-2024-001', 2965.70, 0.00, 2965.70, 'sales_order', 1),
(8, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'Purchase Order PO-2024-001', 0.00, 5550.00, 5550.00, 'purchase_order', 1);

-- ================================================================
-- Step 18: Insert Sample Cash Accounts
-- ================================================================
INSERT INTO cash_accounts (account_id, account_number, bank_name, current_balance, is_active, created_at) VALUES
(3, '1234567890', 'First National Bank', 94450.00, 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 19: Insert Sample Master Production Schedule
-- ================================================================
INSERT INTO master_production_schedule (product_id, period_start, period_end, planned_quantity, actual_quantity, status, is_active, created_at) VALUES
(1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 500, 100, 'active', 1, CURRENT_TIMESTAMP),
(3, DATE_ADD(CURDATE(), INTERVAL 31 DAY), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 300, 0, 'planned', 1, CURRENT_TIMESTAMP);

-- ================================================================
-- Step 20: Insert Sample MRP Run
-- ================================================================
INSERT INTO mrp_runs (run_number, run_date, status, planned_orders_created, notes, created_by, created_at, completed_at) VALUES
('MRP-2024-001', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'completed', 5, 'Initial MRP run for production planning', 1, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 3 DAY), DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 DAY));

-- ================================================================
-- Step 21: Insert Sample Planned Orders
-- ================================================================
INSERT INTO planned_orders (mrp_run_id, item_id, order_type, quantity, planned_date, status, notes) VALUES
(1, 2, 'purchase', 500, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'planned', 'Steel sheets needed for production'),
(1, 3, 'purchase', 300, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'planned', 'Circuit boards for assembly'),
(1, 1, 'production', 250, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'planned', 'Widget production batch');

-- ================================================================
-- ✅ SAMPLE DATA IMPORT COMPLETE!
-- ================================================================
-- Summary:
-- ✓ 3 Companies (1 parent, 2 subsidiaries)
-- ✓ 3 Users (admin, manager, user) - Password: 'password123'
-- ✓ 3 Categories
-- ✓ 3 Units of Measure
-- ✓ 3 Locations
-- ✓ 3 Inventory Items with stock levels
-- ✓ 3 Customers
-- ✓ 3 Suppliers
-- ✓ 3 Sales Orders with line items
-- ✓ 3 Purchase Orders
-- ✓ 3 Work Orders
-- ✓ Bill of Materials entries
-- ✓ Inventory transactions
-- ✓ 15 Chart of Accounts
-- ✓ General Ledger entries
-- ✓ Cash account
-- ✓ Master Production Schedule entries
-- ✓ MRP Run with 3 planned orders
-- ================================================================
