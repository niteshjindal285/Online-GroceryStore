-- ================================================================
-- MJR GROUP ERP – Projects Permissions + Schema Extension
-- Date: 2026-03-30
-- Run this file once to add project permissions to the DB
-- ================================================================

-- 1. Add permissions if they don't exist
INSERT IGNORE INTO permissions (name, description) VALUES
    ('view_projects',   'View projects list and project details'),
    ('manage_projects', 'Create, edit and manage project phases and invoices');

-- 2. Extend project_stages with extra columns (idempotent)
ALTER TABLE project_stages
    ADD COLUMN IF NOT EXISTS due_date   DATE NULL,
    ADD COLUMN IF NOT EXISTS status     ENUM('pending','in_progress','complete','invoiced','paid') DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS invoiced_at  DATETIME NULL,
    ADD COLUMN IF NOT EXISTS sort_order   INT DEFAULT 0;

-- 3. Create project_invoices table
CREATE TABLE IF NOT EXISTS project_invoices (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT NOT NULL,
    stage_id        INT NULL,
    invoice_number  VARCHAR(50) NOT NULL,
    amount          DECIMAL(15,2) DEFAULT 0.00,
    tax_amount      DECIMAL(15,2) DEFAULT 0.00,
    total_amount    DECIMAL(15,2) DEFAULT 0.00,
    status          ENUM('draft','sent','paid','cancelled') DEFAULT 'draft',
    issued_date     DATE NULL,
    due_date        DATE NULL,
    paid_date       DATE NULL,
    notes           TEXT NULL,
    created_by      INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project_id (project_id),
    INDEX idx_stage_id (stage_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id)   REFERENCES project_stages(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Add project reference columns to projects table
ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS project_number VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS start_date     DATE NULL,
    ADD COLUMN IF NOT EXISTS end_date       DATE NULL,
    ADD COLUMN IF NOT EXISTS project_manager VARCHAR(100) NULL;
