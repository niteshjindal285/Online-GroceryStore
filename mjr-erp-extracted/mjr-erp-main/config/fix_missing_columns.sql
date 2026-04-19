-- ================================================================
-- FIX MISSING COLUMNS - Add all missing columns reported in errors
-- ================================================================
-- Date: October 28, 2025
-- Run this script to add missing columns to existing tables
-- ================================================================

USE mjr_group_erp;

-- Fix 1: Add updated_at column to customers table
ALTER TABLE customers 
ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
AFTER created_at;

-- Fix 2: Add reference column to inventory_transactions table
ALTER TABLE inventory_transactions 
ADD COLUMN reference VARCHAR(100) NULL
AFTER unit_cost;

-- Fix 3: Add description column to accounts table
ALTER TABLE accounts 
ADD COLUMN description TEXT NULL
AFTER name;

-- Fix 4: Add updated_at column to journal_entries table
ALTER TABLE journal_entries 
ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
AFTER created_at;

-- Fix 5: Add required_date column to sales_orders table
ALTER TABLE sales_orders 
ADD COLUMN required_date DATE NULL
AFTER order_date;

-- Fix 6: Add expiry_date column to quotes table
ALTER TABLE quotes 
ADD COLUMN expiry_date DATE NULL
AFTER quote_date;

-- Verify the changes
SELECT 'Missing columns have been added successfully!' AS Status;
