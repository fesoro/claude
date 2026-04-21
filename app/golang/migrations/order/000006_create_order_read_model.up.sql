CREATE TABLE order_read_model (
    order_id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    user_name VARCHAR(255) NULL,
    user_email VARCHAR(255) NULL,
    status VARCHAR(32) NOT NULL,
    total_amount BIGINT NOT NULL,
    total_currency CHAR(3) NOT NULL,
    item_count INT NOT NULL DEFAULT 0,
    items_summary JSON NULL,
    address_summary VARCHAR(512) NULL,
    last_updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_orm_user (user_id, last_updated_at),
    INDEX idx_orm_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
