-- Laravel: 2024_xx_create_products_table
-- Aggregate Root: Product
-- VOs: ProductId (uuid), ProductName, Money(amount,currency), Stock
CREATE TABLE products (
    id CHAR(36) NOT NULL PRIMARY KEY COMMENT 'ProductId VO',
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    price_amount BIGINT NOT NULL COMMENT 'qəpiklərlə (cent) saxlanır — Money VO',
    price_currency CHAR(3) NOT NULL COMMENT 'USD | EUR | AZN',
    stock_quantity INT NOT NULL DEFAULT 0,
    -- Optimistic locking (Laravel: VersionedAggregateRoot)
    version BIGINT NOT NULL DEFAULT 0,
    -- Multi-tenancy
    tenant_id CHAR(36) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_currency (price_currency),
    INDEX idx_products_stock (stock_quantity),
    INDEX idx_products_tenant (tenant_id),
    INDEX idx_products_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
