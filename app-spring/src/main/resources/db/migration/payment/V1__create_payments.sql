-- Laravel: 2024_xx_create_payments_table
-- Aggregate Root: Payment
-- State: PENDING → PROCESSING → COMPLETED / FAILED → REFUNDED
CREATE TABLE payments (
    id CHAR(36) NOT NULL PRIMARY KEY COMMENT 'PaymentId VO',
    order_id CHAR(36) NOT NULL COMMENT 'order_db.orders.id-yə referans (cross-context FK YOXDUR)',
    user_id CHAR(36) NOT NULL,
    amount BIGINT NOT NULL COMMENT 'qəpiklə',
    currency CHAR(3) NOT NULL,
    payment_method VARCHAR(32) NOT NULL COMMENT 'CREDIT_CARD | PAYPAL | BANK_TRANSFER',
    status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
    transaction_id VARCHAR(128) NULL COMMENT 'gateway tərəfdən qaytarılan',
    gateway_response JSON NULL,
    failure_reason VARCHAR(512) NULL,
    -- Optimistic locking
    version BIGINT NOT NULL DEFAULT 0,
    -- Multi-tenancy
    tenant_id CHAR(36) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_payments_order (order_id),
    INDEX idx_payments_user (user_id, created_at),
    INDEX idx_payments_status (status),
    INDEX idx_payments_tenant (tenant_id),
    INDEX idx_payments_transaction (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
