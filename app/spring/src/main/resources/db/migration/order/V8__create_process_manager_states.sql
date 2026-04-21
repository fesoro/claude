-- Laravel: PersistentProcessManager → process_manager_states (Saga state)
CREATE TABLE process_manager_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_id CHAR(36) NOT NULL UNIQUE,
    process_type VARCHAR(128) NOT NULL COMMENT 'OrderFulfillmentProcessManager və s.',
    correlation_id VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'STARTED' COMMENT 'STARTED|IN_PROGRESS|COMPLETED|COMPENSATED|FAILED',
    completed_steps JSON NULL COMMENT '["confirmOrder", "processPayment"]',
    state_data JSON NULL COMMENT 'process state',
    started_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_pms_status (status),
    INDEX idx_pms_correlation (correlation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
