ALTER TABLE purchase_orders
ADD COLUMN custom_duty DECIMAL(10,2) DEFAULT 0.00 AFTER other_charges,
ADD COLUMN customs_processing_fees DECIMAL(10,2) DEFAULT 0.00 AFTER custom_duty,
ADD COLUMN quarantine_fees DECIMAL(10,2) DEFAULT 0.00 AFTER customs_processing_fees,
ADD COLUMN excise_tax DECIMAL(10,2) DEFAULT 0.00 AFTER quarantine_fees,
ADD COLUMN shipping_line_anl DECIMAL(10,2) DEFAULT 0.00 AFTER excise_tax,
ADD COLUMN brokerage DECIMAL(10,2) DEFAULT 0.00 AFTER shipping_line_anl,
ADD COLUMN container_detention DECIMAL(10,2) DEFAULT 0.00 AFTER brokerage,
ADD COLUMN bond_refund DECIMAL(10,2) DEFAULT 0.00 AFTER container_detention,
ADD COLUMN cartage DECIMAL(10,2) DEFAULT 0.00 AFTER bond_refund,
ADD COLUMN inspection_fees DECIMAL(10,2) DEFAULT 0.00 AFTER cartage;
