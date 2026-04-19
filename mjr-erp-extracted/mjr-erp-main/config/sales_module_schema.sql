-- ================================================================
-- MJR GROUP ERP - SALES & INVOICING MODULE SCHEMA
-- Date: 2026-03-25
-- Safe to run multiple times (uses IF NOT EXISTS checks)
-- ================================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='';

-- ================================================================
-- 1. EXTEND CUSTOMERS TABLE (Debtor fields)
-- ================================================================

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS credit_term_days    INT DEFAULT 30,
  ADD COLUMN IF NOT EXISTS credit_hold         TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS hold_reason         TEXT NULL,
  ADD COLUMN IF NOT EXISTS discount_tier1_pct  DECIMAL(5,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS discount_tier2_amt  DECIMAL(15,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS credit_approved_by  INT NULL,
  ADD COLUMN IF NOT EXISTS credit_approved_at  DATETIME NULL,
  ADD COLUMN IF NOT EXISTS tax_number          VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS kyc_document_path   VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS credit_pending_approval TINYINT(1) DEFAULT 0;

-- ================================================================
-- 2. EXTEND SALES_ORDERS TABLE
-- ================================================================

ALTER TABLE sales_orders
  ADD COLUMN IF NOT EXISTS order_type          ENUM('standard','cash_sale','internal_factory','foc') DEFAULT 'standard',
  ADD COLUMN IF NOT EXISTS location_id         INT NULL,
  ADD COLUMN IF NOT EXISTS tax_class_id        INT NULL,
  ADD COLUMN IF NOT EXISTS discount_amount     DECIMAL(15,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS payment_method      VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS payment_currency    VARCHAR(10) DEFAULT 'USD',
  ADD COLUMN IF NOT EXISTS payment_date        DATETIME NULL,
  ADD COLUMN IF NOT EXISTS custom_fields       TEXT NULL;

-- ================================================================
-- 3. EXTEND QUOTES TABLE
-- ================================================================

ALTER TABLE quotes
  ADD COLUMN IF NOT EXISTS is_general              TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS general_customer_name   VARCHAR(200) NULL,
  ADD COLUMN IF NOT EXISTS manual_discount         DECIMAL(15,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS tax_class_id            INT NULL,
  ADD COLUMN IF NOT EXISTS company_id              INT NULL DEFAULT 1;

-- ================================================================
-- 4. EXTEND SALES_ORDER_LINES TABLE
-- ================================================================

ALTER TABLE sales_order_lines
  ADD COLUMN IF NOT EXISTS description VARCHAR(500) NULL;

-- ================================================================
-- 5. EXTEND QUOTE_LINES TABLE
-- ================================================================

ALTER TABLE quote_lines
  ADD COLUMN IF NOT EXISTS description VARCHAR(500) NULL;

-- ================================================================
-- 6. DEBTOR DOCUMENTS (KYC File Uploads)
-- ================================================================

CREATE TABLE IF NOT EXISTS debtor_documents (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT NOT NULL,
    file_name     VARCHAR(255) NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    doc_type      VARCHAR(100) NULL COMMENT 'e.g. credit_application, tax_id, id_copy',
    uploaded_by   INT NOT NULL,
    uploaded_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_id (customer_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by)  REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 7. INVOICES
-- ================================================================

CREATE TABLE IF NOT EXISTS invoices (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number  VARCHAR(50) NOT NULL UNIQUE,
    so_id           INT NULL,
    quote_id        INT NULL,
    customer_id     INT NOT NULL,
    company_id      INT NULL DEFAULT 1,
    invoice_date    DATE NOT NULL,
    due_date        DATE NULL,
    subtotal        DECIMAL(15,2) DEFAULT 0.00,
    tax_amount      DECIMAL(15,2) DEFAULT 0.00,
    discount_amount DECIMAL(15,2) DEFAULT 0.00,
    total_amount    DECIMAL(15,2) DEFAULT 0.00,
    amount_paid     DECIMAL(15,2) DEFAULT 0.00,
    payment_status  ENUM('open','closed','cancelled') DEFAULT 'open',
    is_locked       TINYINT(1) DEFAULT 1,
    notes           TEXT NULL,
    cancelled_at    DATETIME NULL,
    cancelled_by    INT NULL,
    created_by      INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_customer_id (customer_id),
    INDEX idx_so_id (so_id),
    INDEX idx_quote_id (quote_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_invoice_date (invoice_date),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (so_id)       REFERENCES sales_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (quote_id)    REFERENCES quotes(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 8. INVOICE LINES
-- ================================================================

CREATE TABLE IF NOT EXISTS invoice_lines (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id      INT NOT NULL,
    item_id         INT NOT NULL,
    description     VARCHAR(500) NULL,
    quantity        DECIMAL(15,4) NOT NULL,
    unit_price      DECIMAL(15,4) NOT NULL,
    discount_pct    DECIMAL(5,2) DEFAULT 0.00,
    line_total      DECIMAL(15,2) DEFAULT 0.00,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_item_id (item_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id)    REFERENCES inventory_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 9. DELIVERY SCHEDULE
-- ================================================================

CREATE TABLE IF NOT EXISTS delivery_schedule (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id      INT NOT NULL,
    status          ENUM('pending','partial','delivered') DEFAULT 'pending',
    scheduled_date  DATE NULL,
    delivered_date  DATE NULL,
    notes           TEXT NULL,
    created_by      INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_status (status),
    FOREIGN KEY (invoice_id)  REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 10. DELIVERY NOTES
-- ================================================================

CREATE TABLE IF NOT EXISTS delivery_notes (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    delivery_schedule_id  INT NOT NULL,
    delivery_number       VARCHAR(50) NOT NULL UNIQUE,
    delivery_date         DATE NOT NULL,
    driver_name           VARCHAR(100) NULL,
    vehicle_number        VARCHAR(50) NULL,
    notes                 TEXT NULL,
    created_by            INT NOT NULL,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_delivery_schedule_id (delivery_schedule_id),
    INDEX idx_delivery_number (delivery_number),
    FOREIGN KEY (delivery_schedule_id) REFERENCES delivery_schedule(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)           REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 11. DELIVERY NOTE LINES
-- ================================================================

CREATE TABLE IF NOT EXISTS delivery_note_lines (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    delivery_note_id    INT NOT NULL,
    invoice_line_id     INT NOT NULL,
    item_id             INT NOT NULL,
    quantity_delivered  DECIMAL(15,4) NOT NULL,
    INDEX idx_delivery_note_id (delivery_note_id),
    INDEX idx_invoice_line_id (invoice_line_id),
    INDEX idx_item_id (item_id),
    FOREIGN KEY (delivery_note_id)  REFERENCES delivery_notes(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_line_id)   REFERENCES invoice_lines(id) ON DELETE RESTRICT,
    FOREIGN KEY (item_id)           REFERENCES inventory_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 12. SALES DISCOUNTS
-- ================================================================

CREATE TABLE IF NOT EXISTS sales_discounts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NULL,
    item_id         INT NULL,
    discount_type   ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    discount_value  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    expiry_date     DATE NULL,
    notes           TEXT NULL,
    status          ENUM('pending','approved','rejected','expired') DEFAULT 'pending',
    approved_by     INT NULL,
    approved_at     DATETIME NULL,
    created_by      INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_id (customer_id),
    INDEX idx_item_id (item_id),
    INDEX idx_status (status),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id)     REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 13. PRICE CHANGES
-- ================================================================

CREATE TABLE IF NOT EXISTS price_changes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    item_id         INT NOT NULL,
    old_price       DECIMAL(15,4) NOT NULL,
    new_price       DECIMAL(15,4) NOT NULL,
    reason          TEXT NULL,
    effective_date  DATE NULL,
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    approved_by     INT NULL,
    approved_at     DATETIME NULL,
    created_by      INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item_id (item_id),
    INDEX idx_status (status),
    FOREIGN KEY (item_id)     REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 14. SALES RETURNS
-- ================================================================

CREATE TABLE IF NOT EXISTS sales_returns (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    return_number   VARCHAR(50) NOT NULL UNIQUE,
    invoice_id      INT NOT NULL,
    reason          TEXT NULL,
    notes           TEXT NULL,
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    stock_restored  TINYINT(1) DEFAULT 0,
    approved_by     INT NULL,
    approved_at     DATETIME NULL,
    created_by      INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_status (status),
    INDEX idx_return_number (return_number),
    FOREIGN KEY (invoice_id)  REFERENCES invoices(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 15. SALES RETURN LINES
-- ================================================================

CREATE TABLE IF NOT EXISTS sales_return_lines (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    return_id       INT NOT NULL,
    invoice_line_id INT NOT NULL,
    item_id         INT NOT NULL,
    quantity        DECIMAL(15,4) NOT NULL,
    unit_price      DECIMAL(15,4) NOT NULL,
    INDEX idx_return_id (return_id),
    INDEX idx_invoice_line_id (invoice_line_id),
    INDEX idx_item_id (item_id),
    FOREIGN KEY (return_id)       REFERENCES sales_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_line_id) REFERENCES invoice_lines(id) ON DELETE RESTRICT,
    FOREIGN KEY (item_id)         REFERENCES inventory_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

-- ================================================================
-- SCHEMA COMPLETE!
-- ================================================================
