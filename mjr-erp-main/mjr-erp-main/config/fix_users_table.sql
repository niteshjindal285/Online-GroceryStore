-- Quick fix for users table column name
-- Run this SQL in your MySQL database to fix the is_active column issue

USE mjr_group_erp;

-- Rename 'active' column to 'is_active' in users table
ALTER TABLE users CHANGE COLUMN active is_active TINYINT(1) DEFAULT 1;
