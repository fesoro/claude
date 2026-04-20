CREATE TABLE circuit_breaker_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(128) NOT NULL UNIQUE,
    state VARCHAR(16) NOT NULL DEFAULT 'CLOSED',
    failure_count INT NOT NULL DEFAULT 0,
    last_failure_at TIMESTAMP NULL,
    next_attempt_at TIMESTAMP NULL,
    last_state_change_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cb_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
