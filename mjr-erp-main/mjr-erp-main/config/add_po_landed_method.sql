ALTER TABLE purchase_orders
ADD COLUMN landed_cost_method VARCHAR(20) DEFAULT 'value' AFTER currency_id;

ALTER TABLE purchase_order_lines
ADD COLUMN cbm_length DECIMAL(10,4) DEFAULT 0.0000 AFTER unit_price,
ADD COLUMN cbm_width DECIMAL(10,4) DEFAULT 0.0000 AFTER cbm_length,
ADD COLUMN cbm_height DECIMAL(10,4) DEFAULT 0.0000 AFTER cbm_width,
ADD COLUMN cbm_total DECIMAL(10,4) DEFAULT 0.0000 AFTER cbm_height,
ADD COLUMN manual_pct DECIMAL(5,2) DEFAULT 0.00 AFTER cbm_total;
