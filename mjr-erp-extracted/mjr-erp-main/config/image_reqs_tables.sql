USE kustom_mjr;

-- Add description fields to lines
ALTER TABLE sales_order_lines ADD COLUMN description TEXT NULL AFTER item_id;
ALTER TABLE quote_lines ADD COLUMN description TEXT NULL AFTER item_id;
ALTER TABLE purchase_order_lines ADD COLUMN description TEXT NULL AFTER item_id;

-- Create customer_discounts table
CREATE TABLE IF NOT EXISTS customer_discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    item_id INT NULL COMMENT 'If NULL, applies to all items for this customer',
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    UNIQUE KEY idx_customer_item (customer_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
