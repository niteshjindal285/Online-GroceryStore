-- ================================================================
-- MJR GROUP ERP - PROJECT MANAGEMENT & MILESTONE BILLING SCHEMA
-- Date: 2026-03-27
-- ================================================================

SET FOREIGN_KEY_CHECKS=0;

-- Drop old tables to avoid conflicts
DROP TABLE IF EXISTS project_variations;
DROP TABLE IF EXISTS project_stages;
DROP TABLE IF EXISTS projects;

-- 1. PROJECTS TABLE
CREATE TABLE projects (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    customer_id     INT NOT NULL,
    total_value     DECIMAL(15,2) DEFAULT 0.00,
    status          ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_by      INT NOT NULL,
    company_id      INT DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_id (customer_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. PROJECT STAGES (Milestones)
CREATE TABLE IF NOT EXISTS project_stages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT NOT NULL,
    stage_name      VARCHAR(255) NOT NULL,
    percentage      DECIMAL(5,2) DEFAULT 0.00,
    amount          DECIMAL(15,2) DEFAULT 0.00,
    details         TEXT NULL,
    status          ENUM('pending', 'invoiced', 'paid') DEFAULT 'pending',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_id (project_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. PROJECT VARIATIONS (Extra Costs)
CREATE TABLE IF NOT EXISTS project_variations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT NOT NULL,
    stage_id        INT NULL,
    description     TEXT NOT NULL,
    amount          DECIMAL(15,2) DEFAULT 0.00,
    status          ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_by      INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_id (project_id),
    INDEX idx_stage_id (stage_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id)   REFERENCES project_stages(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. EXTEND SALES_ORDERS AND INVOICES
ALTER TABLE sales_orders
    ADD COLUMN IF NOT EXISTS sale_type ENUM('normal', 'project') DEFAULT 'normal',
    ADD COLUMN IF NOT EXISTS project_id INT NULL,
    ADD COLUMN IF NOT EXISTS project_stage_id INT NULL,
    ADD CONSTRAINT fk_so_project FOREIGN KEY IF NOT EXISTS (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_so_stage FOREIGN KEY IF NOT EXISTS (project_stage_id) REFERENCES project_stages(id) ON DELETE SET NULL;

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS sale_type ENUM('normal', 'project') DEFAULT 'normal',
    ADD COLUMN IF NOT EXISTS project_id INT NULL,
    ADD COLUMN IF NOT EXISTS project_stage_id INT NULL,
    ADD CONSTRAINT fk_inv_project FOREIGN KEY IF NOT EXISTS (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_inv_stage FOREIGN KEY IF NOT EXISTS (project_stage_id) REFERENCES project_stages(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS=1;
