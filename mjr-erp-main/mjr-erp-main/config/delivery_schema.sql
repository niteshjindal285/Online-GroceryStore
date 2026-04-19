-- ================================================================
-- MJR GROUP ERP - DELIVERY & PARTIAL FULFILLMENT SCHEMA
-- Date: 2026-03-27
-- ================================================================

SET FOREIGN_KEY_CHECKS=0;

-- 1. DELIVERIES TABLE (Header)
CREATE TABLE IF NOT EXISTS deliveries (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    delivery_number VARCHAR(50) NOT NULL UNIQUE,
    invoice_id      INT NULL,
    order_id        INT NULL,
    delivery_date   DATETIME DEFAULT CURRENT_TIMESTAMP,
    status          ENUM('shipped', 'delivered', 'returned', 'cancelled') DEFAULT 'shipped',
    courier_name    VARCHAR(100) NULL,
    tracking_number VARCHAR(100) NULL,
    notes           TEXT NULL,
    created_by      INT NOT NULL,
    company_id      INT DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_order_id (order_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id)   REFERENCES sales_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. DELIVERY ITEMS TABLE (Line level tracking)
CREATE TABLE IF NOT EXISTS delivery_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id         INT NOT NULL,
    sales_order_line_id INT NOT NULL,
    item_id             INT NOT NULL,
    quantity_delivered  DECIMAL(15,4) NOT NULL,
    notes               TEXT NULL,
    INDEX idx_delivery_id (delivery_id),
    INDEX idx_so_line (sales_order_line_id),
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (sales_order_line_id) REFERENCES sales_order_lines(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. EXTEND SALES_ORDERS FOR DELIVERY STATUS
ALTER TABLE sales_orders
    ADD COLUMN IF NOT EXISTS delivery_status ENUM('open', 'pending', 'completed') DEFAULT 'open';

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS delivery_status ENUM('open', 'pending', 'completed') DEFAULT 'open';

SET FOREIGN_KEY_CHECKS=1;
