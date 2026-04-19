ALTER TABLE work_orders ADD COLUMN custom_fields TEXT AFTER notes;
ALTER TABLE inventory_items ADD COLUMN custom_fields TEXT AFTER description;
ALTER TABLE locations ADD COLUMN custom_fields TEXT AFTER address;
