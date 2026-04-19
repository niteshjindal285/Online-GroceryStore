-- ================================================================
-- MJR GROUP ERP - BOM SYSTEM SCHEMA
-- ================================================================
-- Screen 2: Production Configuration / BOM Screen
-- ================================================================

USE kustom_mjr;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Product Ranges
-- Section 6: Product Range Management
CREATE TABLE IF NOT EXISTS product_ranges (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    range_name      VARCHAR(200) NOT NULL,
    category_id     INT NULL,
    variant_name    VARCHAR(100) NULL,
    capacity_range  VARCHAR(100) NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. BOM Headers
-- Section 1, 3, 4: BOM Metadata and Costs
CREATE TABLE IF NOT EXISTS bom_headers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    bom_number          VARCHAR(50) NOT NULL UNIQUE, -- BOM-00001
    product_id          INT NOT NULL,               -- Link to finished product
    category_id         INT NULL,
    product_capacity    VARCHAR(100) NULL,
    unit_id             INT NULL,                   -- From units_of_measure
    location_id         INT NULL,                   -- Production Location
    status              ENUM('Draft', 'Active', 'Archived') DEFAULT 'Draft',
    remarks             TEXT NULL,
    
    -- Additional Manufacturing Costs (Section 3)
    labor_cost          DECIMAL(15,2) DEFAULT 0.00,
    electricity_cost    DECIMAL(15,2) DEFAULT 0.00,
    machine_cost        DECIMAL(15,2) DEFAULT 0.00,
    maintenance_cost    DECIMAL(15,2) DEFAULT 0.00,
    other_cost          DECIMAL(15,2) DEFAULT 0.00,
    
    -- Totals (Section 4)
    total_material_cost DECIMAL(15,2) DEFAULT 0.00,
    total_additional_cost DECIMAL(15,2) DEFAULT 0.00,
    total_production_cost DECIMAL(15,2) DEFAULT 0.00,
    cost_per_unit       DECIMAL(15,2) DEFAULT 0.00,
    
    created_by          INT NOT NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id) REFERENCES units_of_measure(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. BOM Items
-- Section 2: Raw Material Requirements
CREATE TABLE IF NOT EXISTS bom_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    bom_id              INT NOT NULL,
    item_id             INT NOT NULL,               -- Raw Material
    warehouse_id        INT NULL,                   -- Source Warehouse
    quantity_required   DECIMAL(15,4) NOT NULL,
    unit_cost           DECIMAL(15,4) DEFAULT 0.00, -- Snapshot of cost at time of BOM
    total_cost          DECIMAL(15,2) AS (quantity_required * unit_cost) VIRTUAL,
    notes               TEXT NULL,
    
    FOREIGN KEY (bom_id) REFERENCES bom_headers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
