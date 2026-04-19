-- ================================================================
-- ADD TRANSACTION-LEVEL TAX CLASS SELECTION
-- ================================================================
-- This script adds tax_class_id to all transaction tables
-- Enables dynamic tax selection at order/quote/work order/PO level
-- Date: November 3, 2025
-- ================================================================

USE mjr_group_erp;

-- ================================================================
-- ADD TAX_CLASS_ID TO SALES ORDERS
-- ================================================================

ALTER TABLE sales_orders 
    ADD COLUMN IF NOT EXISTS tax_class_id INT NULL 
    COMMENT 'Selected tax class for this order'
    AFTER total_amount;

-- Add index for performance
ALTER TABLE sales_orders 
    ADD INDEX IF NOT EXISTS idx_tax_class_id (tax_class_id);

-- Add foreign key constraint
ALTER TABLE sales_orders 
    ADD CONSTRAINT fk_sales_orders_tax_class 
    FOREIGN KEY (tax_class_id) REFERENCES tax_configurations(id) ON DELETE RESTRICT;

-- ================================================================
-- ADD TAX_CLASS_ID TO QUOTES
-- ================================================================

ALTER TABLE quotes 
    ADD COLUMN IF NOT EXISTS tax_class_id INT NULL 
    COMMENT 'Selected tax class for this quote'
    AFTER total_amount;

-- Add index for performance
ALTER TABLE quotes 
    ADD INDEX IF NOT EXISTS idx_tax_class_id (tax_class_id);

-- Add foreign key constraint
ALTER TABLE quotes 
    ADD CONSTRAINT fk_quotes_tax_class 
    FOREIGN KEY (tax_class_id) REFERENCES tax_configurations(id) ON DELETE RESTRICT;

-- ================================================================
-- ADD TAX_CLASS_ID TO WORK ORDERS
-- ================================================================

ALTER TABLE work_orders 
    ADD COLUMN IF NOT EXISTS tax_class_id INT NULL 
    COMMENT 'Selected tax class for this work order (if applicable)'
    AFTER status;

-- Add index for performance
ALTER TABLE work_orders 
    ADD INDEX IF NOT EXISTS idx_tax_class_id (tax_class_id);

-- Add foreign key constraint
ALTER TABLE work_orders 
    ADD CONSTRAINT fk_work_orders_tax_class 
    FOREIGN KEY (tax_class_id) REFERENCES tax_configurations(id) ON DELETE RESTRICT;

-- ================================================================
-- ADD TAX_CLASS_ID TO PURCHASE ORDERS
-- ================================================================

ALTER TABLE purchase_orders 
    ADD COLUMN IF NOT EXISTS tax_class_id INT NULL 
    COMMENT 'Selected tax class for this purchase order'
    AFTER total_amount;

-- Add index for performance
ALTER TABLE purchase_orders 
    ADD INDEX IF NOT EXISTS idx_tax_class_id (tax_class_id);

-- Add foreign key constraint
ALTER TABLE purchase_orders 
    ADD CONSTRAINT fk_purchase_orders_tax_class 
    FOREIGN KEY (tax_class_id) REFERENCES tax_configurations(id) ON DELETE RESTRICT;

-- ================================================================
-- VERIFICATION QUERIES
-- ================================================================

-- Verify sales_orders has tax_class_id
SELECT 'Sales Orders - tax_class_id column:' as Info;
SHOW COLUMNS FROM sales_orders LIKE 'tax_class_id';

-- Verify quotes has tax_class_id
SELECT 'Quotes - tax_class_id column:' as Info;
SHOW COLUMNS FROM quotes LIKE 'tax_class_id';

-- Verify work_orders has tax_class_id
SELECT 'Work Orders - tax_class_id column:' as Info;
SHOW COLUMNS FROM work_orders LIKE 'tax_class_id';

-- Verify purchase_orders has tax_class_id
SELECT 'Purchase Orders - tax_class_id column:' as Info;
SHOW COLUMNS FROM purchase_orders LIKE 'tax_class_id';

-- Check foreign keys
SELECT 'Foreign Keys Verification:' as Info;
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'mjr_group_erp'
AND COLUMN_NAME = 'tax_class_id'
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- ================================================================
-- NOTES
-- ================================================================
-- After running this script:
-- 1. All transaction tables can store selected tax class
-- 2. Tax calculation changes from per-item to transaction-level
-- 3. Users can select tax rate when creating orders/quotes/POs/WOs
-- 4. Tax is calculated as: subtotal × selected_tax_rate
-- 5. NULL tax_class_id means no tax (0%)
-- ================================================================

SELECT 'Transaction tax class columns added successfully!' as Status;
