-- ================================================================
-- FIX ADDITIONAL MISSING COLUMNS - Extended Migration Script
-- ================================================================
-- This script fixes ALL reported database column errors from the log
-- Run this AFTER fix_all_missing_columns.sql
-- Date: October 2025
-- ================================================================

USE mjr_group_erp;

-- ================================================================
-- FIX 1: Accounts Table - Add updated_at Column
-- ================================================================
-- Error: "Unknown column 'updated_at' in 'field list'"

ALTER TABLE accounts 
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;

-- Set updated_at to created_at for existing records
UPDATE accounts SET updated_at = created_at WHERE updated_at IS NULL;

-- ================================================================
-- FIX 2: Sales Orders Table - Add company_id Column
-- ================================================================
-- Error: "Unknown column 'company_id' in 'field list'"

ALTER TABLE sales_orders 
    ADD COLUMN IF NOT EXISTS company_id INT NULL DEFAULT 1
    AFTER customer_id;

-- Set company_id based on customer's company (if customers have company_id)
-- Otherwise default to 1
UPDATE sales_orders so
LEFT JOIN customers c ON so.customer_id = c.id
SET so.company_id = COALESCE(c.company_id, 1)
WHERE so.company_id IS NULL;

-- Add index for performance
ALTER TABLE sales_orders 
    ADD INDEX IF NOT EXISTS idx_company_id (company_id);

-- Add foreign key constraint
ALTER TABLE sales_orders 
    ADD CONSTRAINT fk_sales_orders_company 
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT;

-- ================================================================
-- FIX 3: Quotes Table - Add company_id Column (if not exists)
-- ================================================================

ALTER TABLE quotes 
    ADD COLUMN IF NOT EXISTS company_id INT NULL DEFAULT 1
    AFTER customer_id;

-- Set company_id for existing quotes
UPDATE quotes q
LEFT JOIN customers c ON q.customer_id = c.id
SET q.company_id = COALESCE(c.company_id, 1)
WHERE q.company_id IS NULL;

-- Add index
ALTER TABLE quotes 
    ADD INDEX IF NOT EXISTS idx_company_id (company_id);

-- ================================================================
-- FIX 4: Customers Table - Ensure company_id exists
-- ================================================================

ALTER TABLE customers 
    ADD COLUMN IF NOT EXISTS company_id INT NULL DEFAULT 1
    AFTER address;

-- Set default company for existing customers
UPDATE customers SET company_id = 1 WHERE company_id IS NULL;

-- Add index
ALTER TABLE customers 
    ADD INDEX IF NOT EXISTS idx_company_id (company_id);

-- ================================================================
-- FIX 5: Purchase Orders Table - Add company_id (if missing)
-- ================================================================

ALTER TABLE purchase_orders 
    ADD COLUMN IF NOT EXISTS company_id INT NULL DEFAULT 1
    AFTER supplier_id;

-- Set default company
UPDATE purchase_orders SET company_id = 1 WHERE company_id IS NULL;

-- Add index
ALTER TABLE purchase_orders 
    ADD INDEX IF NOT EXISTS idx_company_id (company_id);

-- ================================================================
-- FIX 6: Ensure inventory_items has all required columns
-- ================================================================

-- Reorder level should already exist, but verify it's in the right place
-- This fixes the dashboard error: "Unknown column 'i.reorder_level' in 'having clause'"

-- Check if reorder_level exists, if not add it
ALTER TABLE inventory_items 
    ADD COLUMN IF NOT EXISTS reorder_level INT DEFAULT 0
    AFTER selling_price;

ALTER TABLE inventory_items 
    ADD COLUMN IF NOT EXISTS reorder_quantity INT DEFAULT 0
    AFTER reorder_level;

-- ================================================================
-- FIX 7: Ensure BOM table has proper indexes for validation
-- ================================================================

-- Add composite index to help with BOM validation
ALTER TABLE bill_of_materials 
    ADD INDEX IF NOT EXISTS idx_product_component (product_id, component_id);

-- ================================================================
-- FIX 8: Journal Entries - Add updated_at if missing
-- ================================================================

ALTER TABLE journal_entries 
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;

-- Set updated_at to created_at for existing records
UPDATE journal_entries SET updated_at = created_at WHERE updated_at IS NULL;

-- ================================================================
-- FIX 9: Ensure locations has proper columns
-- ================================================================

-- Verify locations has all required fields
ALTER TABLE locations 
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1
    AFTER address;

-- ================================================================
-- FIX 10: Add indexes for better performance
-- ================================================================

-- Sales orders
ALTER TABLE sales_orders 
    ADD INDEX IF NOT EXISTS idx_order_date (order_date),
    ADD INDEX IF NOT EXISTS idx_status (status);

-- Quotes
ALTER TABLE quotes 
    ADD INDEX IF NOT EXISTS idx_quote_date (quote_date),
    ADD INDEX IF NOT EXISTS idx_status (status);

-- Customers
ALTER TABLE customers 
    ADD INDEX IF NOT EXISTS idx_customer_code (customer_code);

-- Suppliers
ALTER TABLE suppliers 
    ADD COLUMN IF NOT EXISTS company_id INT NULL DEFAULT 1;
ALTER TABLE suppliers 
    ADD INDEX IF NOT EXISTS idx_supplier_code (supplier_code);

-- ================================================================
-- VERIFICATION QUERIES
-- ================================================================

-- Verify accounts has updated_at
SELECT 'Accounts Table - updated_at column:' as Info;
SHOW COLUMNS FROM accounts LIKE 'updated_at';

-- Verify sales_orders has company_id
SELECT 'Sales Orders Table - company_id column:' as Info;
SHOW COLUMNS FROM sales_orders LIKE 'company_id';

-- Verify inventory_items has reorder columns
SELECT 'Inventory Items Table - reorder columns:' as Info;
SHOW COLUMNS FROM inventory_items LIKE 'reorder_%';

-- Verify journal_entries has updated_at
SELECT 'Journal Entries Table - updated_at column:' as Info;
SHOW COLUMNS FROM journal_entries LIKE 'updated_at';

-- Check all foreign keys are in place
SELECT 'Foreign Keys on sales_orders:' as Info;
SELECT 
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'mjr_group_erp'
AND TABLE_NAME = 'sales_orders'
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- ================================================================
-- NOTES
-- ================================================================
-- After running this script:
-- 1. All database column errors should be resolved
-- 2. Foreign key relationships are properly set up
-- 3. Indexes are in place for better performance
-- 4. Multi-company support is enabled across all modules
-- ================================================================

SELECT 'Additional column migration completed successfully!' as Status;
