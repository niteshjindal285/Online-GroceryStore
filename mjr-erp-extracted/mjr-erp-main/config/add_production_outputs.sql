-- Database schema update for multi-product output in Production Orders
-- Allows storing a 1-to-many relationship for Steel/Variable manufacturing models

USE kustom_mjr;

-- 1. Create work_order_outputs table
CREATE TABLE IF NOT EXISTS work_order_outputs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    measurement_length DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    measurement_width DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    total_meters DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    allocated_width DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    allocated_consumption DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    cost DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add Manufacturing Model & Raw Material tracking to work_orders
ALTER TABLE work_orders 
ADD COLUMN manufacturing_model ENUM('fixed', 'variable') NOT NULL DEFAULT 'fixed' AFTER fg_bin_id,
ADD COLUMN input_raw_material_id INT NULL AFTER manufacturing_model,
ADD CONSTRAINT fk_wo_raw_material FOREIGN KEY (input_raw_material_id) REFERENCES inventory_items(id);

-- If product_id allows NULL in variable models (where a single product_id isn't representative enough)
-- we should make product_id nullable or leave it alone and just ignore it for variable models.
-- We will ALTER product_id to allow NULL because a single order may produce multiple distinct products.
ALTER TABLE work_orders MODIFY COLUMN product_id INT NULL;
