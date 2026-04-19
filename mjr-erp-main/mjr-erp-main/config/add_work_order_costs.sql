-- Add cost and tax tracking to work_orders table
-- Run this migration to add transaction-level cost and tax fields

ALTER TABLE work_orders 
ADD COLUMN estimated_cost DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Estimated production cost',
ADD COLUMN material_cost DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Actual material cost',
ADD COLUMN labor_cost DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Labor cost',
ADD COLUMN overhead_cost DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Overhead cost',
ADD COLUMN subtotal DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Subtotal before tax',
ADD COLUMN tax_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Tax amount',
ADD COLUMN total_cost DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Total cost including tax',
ADD COLUMN tax_class_id INT NULL COMMENT 'Tax class for this work order',
ADD CONSTRAINT fk_work_orders_tax_class 
    FOREIGN KEY (tax_class_id) REFERENCES tax_configurations(id) ON DELETE SET NULL;

-- Add index for tax_class_id
CREATE INDEX idx_work_orders_tax_class ON work_orders(tax_class_id);
