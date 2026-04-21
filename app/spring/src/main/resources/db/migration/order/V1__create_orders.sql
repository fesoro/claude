-- Laravel: 2024_xx_create_orders_table
-- Aggregate Root: Order
-- State machine: PENDING → CONFIRMED → PAID → SHIPPED → DELIVERED / CANCELLED
CREATE TABLE orders (
    id CHAR(36) NOT NULL PRIMARY KEY COMMENT 'OrderId VO',
    user_id CHAR(36) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
    total_amount BIGINT NOT NULL COMMENT 'qəpiklərlə',
    total_currency CHAR(3) NOT NULL,
    -- Address VO (embedded)
    address_street VARCHAR(255) NOT NULL,
    address_city VARCHAR(128) NOT NULL,
    address_zip VARCHAR(32) NOT NULL,
    address_country VARCHAR(64) NOT NULL,
    -- Optimistic locking
    version BIGINT NOT NULL DEFAULT 0,
    -- Multi-tenancy
    tenant_id CHAR(36) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_user_created (user_id, created_at),
    INDEX idx_orders_status (status),
    INDEX idx_orders_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
