-- Laravel: DeadLetterQueue → dead_letter_messages
-- RabbitMQ-də mesaj N dəfə fail olarsa, DLQ-a düşür
CREATE TABLE dead_letter_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_message_id CHAR(36) NULL,
    queue_name VARCHAR(128) NOT NULL,
    event_type VARCHAR(128) NOT NULL,
    payload JSON NOT NULL,
    error_message TEXT NULL,
    error_class VARCHAR(255) NULL,
    stack_trace TEXT NULL,
    failed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    retried BOOLEAN NOT NULL DEFAULT FALSE,
    INDEX idx_dlq_queue (queue_name, failed_at),
    INDEX idx_dlq_retried (retried)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
