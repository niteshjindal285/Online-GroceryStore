-- Production & MRP Schema Enhancement
-- BOM WIP Sub-items + Quality Control Tables
-- Database: kustom_mjr
-- Run this in XAMPP phpMyAdmin or MySQL CLI

USE kustom_mjr;

-- ══════════════════════════════════════════════════════════════
-- PHASE 1: BOM WIP Sub-items Support
-- ══════════════════════════════════════════════════════════════

-- Add item_type to bill_of_materials
ALTER TABLE bill_of_materials
    ADD COLUMN IF NOT EXISTS item_type ENUM('raw_material','wip','sub_assembly','finished_good') NOT NULL DEFAULT 'raw_material' AFTER component_id,
    ADD COLUMN IF NOT EXISTS parent_bom_id INT NULL AFTER item_type,
    ADD COLUMN IF NOT EXISTS bom_level INT NOT NULL DEFAULT 0 AFTER parent_bom_id,
    ADD COLUMN IF NOT EXISTS operation_sequence INT NOT NULL DEFAULT 10 AFTER bom_level,
    ADD COLUMN IF NOT EXISTS scrap_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER operation_sequence;


-- ══════════════════════════════════════════════════════════════
-- PHASE 2: Master Production Schedule — add priority & notes
-- ══════════════════════════════════════════════════════════════

ALTER TABLE master_production_schedule
    ADD COLUMN IF NOT EXISTS priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal' AFTER status,
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER priority,
    ADD COLUMN IF NOT EXISTS work_order_id INT NULL AFTER notes;

-- ══════════════════════════════════════════════════════════════
-- PHASE 3: Quality Control Tables
-- ══════════════════════════════════════════════════════════════

-- QC Plans: template checklists per product
CREATE TABLE IF NOT EXISTS qc_plans (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    company_id  INT NOT NULL DEFAULT 1,
    plan_name   VARCHAR(200) NOT NULL,
    product_id  INT NULL,                          -- NULL = applies to all products
    applies_to  ENUM('incoming','in_process','final','all') NOT NULL DEFAULT 'final',
    description TEXT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_product (product_id)
);

-- QC Check Items: individual checks within a plan
CREATE TABLE IF NOT EXISTS qc_check_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    plan_id         INT NOT NULL,
    check_name      VARCHAR(200) NOT NULL,
    check_type      ENUM('visual','measurement','functional','dimensional','weight','other') NOT NULL DEFAULT 'visual',
    description     TEXT NULL,
    acceptance_criteria TEXT NULL,
    min_value       DECIMAL(10,4) NULL,
    max_value       DECIMAL(10,4) NULL,
    unit            VARCHAR(30) NULL,
    is_critical     TINYINT(1) NOT NULL DEFAULT 0,  -- Critical = one fail → whole inspection fails
    sequence_order  INT NOT NULL DEFAULT 10,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (plan_id) REFERENCES qc_plans(id) ON DELETE CASCADE,
    INDEX idx_plan (plan_id)
);

-- QC Inspections: one inspection run per work order stage
CREATE TABLE IF NOT EXISTS qc_inspections (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    company_id      INT NOT NULL DEFAULT 1,
    inspection_no   VARCHAR(30) NOT NULL UNIQUE,
    work_order_id   INT NULL,
    product_id      INT NULL,
    plan_id         INT NULL,
    inspection_type ENUM('incoming','in_process','final','ad_hoc') NOT NULL DEFAULT 'final',
    inspector_id    INT NOT NULL DEFAULT 1,
    inspected_qty   INT NOT NULL DEFAULT 1,
    passed_qty      INT NOT NULL DEFAULT 0,
    failed_qty      INT NOT NULL DEFAULT 0,
    overall_result  ENUM('pending','pass','fail','conditional') NOT NULL DEFAULT 'pending',
    remarks         TEXT NULL,
    inspection_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_work_order (work_order_id),
    INDEX idx_product (product_id),
    INDEX idx_result (overall_result),
    INDEX idx_date (inspection_date)
);

-- QC Inspection Results: individual check item results
CREATE TABLE IF NOT EXISTS qc_inspection_results (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id   INT NOT NULL,
    check_item_id   INT NULL,
    check_name      VARCHAR(200) NOT NULL,
    check_type      VARCHAR(50) NOT NULL DEFAULT 'visual',
    measured_value  VARCHAR(100) NULL,
    result          ENUM('pass','fail','na') NOT NULL DEFAULT 'na',
    remarks         TEXT NULL,
    is_critical     TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (inspection_id) REFERENCES qc_inspections(id) ON DELETE CASCADE,
    INDEX idx_inspection (inspection_id)
);

-- ══════════════════════════════════════════════════════════════
-- SAMPLE DATA: QC Plans
-- ══════════════════════════════════════════════════════════════

INSERT IGNORE INTO qc_plans (id, company_id, plan_name, product_id, applies_to, description, is_active, created_by) VALUES
(1, 1, 'Standard Final Inspection', NULL, 'final', 'Standard quality checks applied to all finished goods before dispatch.', 1, 1),
(2, 1, 'In-Process Quality Check', NULL, 'in_process', 'Mid-production checks to catch defects early during manufacturing.', 1, 1),
(3, 1, 'Incoming Material Inspection', NULL, 'incoming', 'Quality checks for raw materials received from suppliers.', 1, 1);

INSERT IGNORE INTO qc_check_items (plan_id, check_name, check_type, description, acceptance_criteria, is_critical, sequence_order) VALUES
-- Plan 1: Final
(1, 'Visual Surface Inspection', 'visual', 'Check for scratches, dents, discoloration, or surface defects', 'No visible defects larger than 1mm', 0, 10),
(1, 'Dimensional Check — Length', 'dimensional', 'Measure product length with calibrated caliper', 'Within ±0.5mm of specification', 1, 20),
(1, 'Dimensional Check — Width', 'dimensional', 'Measure product width with calibrated caliper', 'Within ±0.5mm of specification', 1, 30),
(1, 'Weight Verification', 'weight', 'Weigh product on calibrated scale', 'Within ±2% of nominal weight', 0, 40),
(1, 'Functional Test', 'functional', 'Operate product through full cycle to verify function', 'All functions operate as designed', 1, 50),
(1, 'Packaging & Labeling', 'visual', 'Verify correct packaging, labels, and markings', 'Label matches product spec, no damage', 0, 60),
-- Plan 2: In-Process
(2, 'Assembly Completeness', 'visual', 'Verify all components assembled correctly', 'All BOM components present and secured', 1, 10),
(2, 'Weld/Joint Integrity', 'visual', 'Inspect all joints and welds', 'No gaps, cracks, or incomplete bonds', 1, 20),
(2, 'Torque Check', 'functional', 'Verify all fasteners to correct torque spec', 'Within ±10% of specified torque', 0, 30),
-- Plan 3: Incoming
(3, 'Material Certificate Review', 'visual', 'Verify material test certificates match order', 'Certificates present and matching', 1, 10),
(3, 'Quantity Count', 'dimensional', 'Count or weigh received goods vs. PO quantity', 'Within ±1% of PO quantity', 0, 20),
(3, 'Surface Condition', 'visual', 'Check raw material for damage, rust, or contamination', 'No damage, rust, or contamination', 0, 30);
