-- ================================================================
-- FIX ALL MISSING COLUMNS - Complete Migration Script
-- ================================================================
-- This script fixes ALL reported database column errors
-- Run this on your existing database to add missing columns
-- Date: October 2025
-- ================================================================

USE mjr_group_erp;

-- ================================================================
-- FIX 1: Tax Configurations Table
-- ================================================================
-- The tax_configurations table needs major restructuring to match
-- the application requirements

-- Check if old columns exist and rename/add new ones
ALTER TABLE tax_configurations 
    ADD COLUMN IF NOT EXISTS tax_name VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS tax_code VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS tax_type VARCHAR(20) NULL COMMENT 'sales_tax, purchase_tax, withholding',
    ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,4) NULL COMMENT 'Rate as decimal (0.10 for 10%)',
    ADD COLUMN IF NOT EXISTS tax_account_id INT NULL,
    ADD COLUMN IF NOT EXISTS company_id INT NULL DEFAULT 1;

-- If old 'name' column exists, copy data to 'tax_name'
UPDATE tax_configurations 
SET tax_name = name 
WHERE tax_name IS NULL AND name IS NOT NULL;

-- If old 'rate' column exists (DECIMAL(5,2)), convert to new format (DECIMAL(5,4))
-- Note: Old rate was percentage (10.00), new is decimal (0.1000)
UPDATE tax_configurations 
SET tax_rate = rate / 100 
WHERE tax_rate IS NULL AND rate IS NOT NULL;

-- Set default values for new required columns
UPDATE tax_configurations 
SET tax_code = UPPER(SUBSTRING(tax_name, 1, 10))
WHERE tax_code IS NULL AND tax_name IS NOT NULL;

UPDATE tax_configurations 
SET tax_type = 'sales_tax'
WHERE tax_type IS NULL;

-- Make new columns NOT NULL after data migration
ALTER TABLE tax_configurations 
    MODIFY COLUMN tax_name VARCHAR(100) NOT NULL,
    MODIFY COLUMN tax_code VARCHAR(20) NOT NULL,
    MODIFY COLUMN tax_type VARCHAR(20) NOT NULL,
    MODIFY COLUMN tax_rate DECIMAL(5,4) NOT NULL;

-- Add unique constraint on tax_code
ALTER TABLE tax_configurations 
    ADD UNIQUE INDEX idx_tax_code (tax_code);

-- Add foreign key for tax_account_id
ALTER TABLE tax_configurations 
    ADD CONSTRAINT fk_tax_account 
    FOREIGN KEY (tax_account_id) REFERENCES accounts(id) ON DELETE RESTRICT;

-- Add foreign key for company_id
ALTER TABLE tax_configurations 
    ADD CONSTRAINT fk_tax_company 
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT;

-- ================================================================
-- FIX 2: Work Orders Table - Add 'active' Column
-- ================================================================

ALTER TABLE work_orders 
    ADD COLUMN IF NOT EXISTS active TINYINT(1) DEFAULT 1 COMMENT 'Active status flag';

-- Set all existing work orders to active
UPDATE work_orders SET active = 1 WHERE active IS NULL;

-- Make it NOT NULL
ALTER TABLE work_orders 
    MODIFY COLUMN active TINYINT(1) NOT NULL DEFAULT 1;

-- Add index for performance
ALTER TABLE work_orders 
    ADD INDEX IF NOT EXISTS idx_active (active);

-- ================================================================
-- VERIFICATION QUERIES
-- ================================================================
-- Run these to verify the changes were applied correctly

-- Verify tax_configurations structure
SELECT 'Tax Configurations Table Structure:' as Info;
DESCRIBE tax_configurations;

-- Verify work_orders has active column
SELECT 'Work Orders Table Structure (checking active column):' as Info;
SHOW COLUMNS FROM work_orders LIKE 'active';

-- Count records in each table
SELECT 'Tax Configurations Count:' as Info, COUNT(*) as count FROM tax_configurations;
SELECT 'Work Orders Count:' as Info, COUNT(*) as count FROM work_orders;

-- ================================================================
-- NOTES
-- ================================================================
-- After running this script:
-- 1. Tax management should work without column errors
-- 2. Work orders should work without 'active' column errors
-- 3. All existing data is preserved
-- 4. You can now add new tax classes through the UI
-- ================================================================

SELECT 'Migration completed successfully!' as Status;
