CREATE TABLE inbox_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id CHAR(36) NOT NULL UNIQUE,
    event_type VARCHAR(128) NOT NULL,
    payload JSON NOT NULL,
    received_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_inbox_processed (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
