-- Per-context transactional outbox (User DB)
-- Business data + outbox message bir tranzaksiyada → exactly-once guarantee
CREATE TABLE outbox_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id CHAR(36) NOT NULL UNIQUE,
    aggregate_id CHAR(36) NULL,
    event_type VARCHAR(128) NOT NULL,
    routing_key VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    metadata JSON NULL,
    published BOOLEAN NOT NULL DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    retry_count SMALLINT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_outbox_unpublished (published, created_at),
    INDEX idx_outbox_aggregate (aggregate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
