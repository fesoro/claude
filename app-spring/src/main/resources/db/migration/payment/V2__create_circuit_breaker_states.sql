-- Laravel: CircuitBreaker → circuit_breaker_states
-- Spring-də Resilience4j bunu in-memory edir, amma cluster-dağıtık state üçün
-- DB-də saxlamaq vacibdir (multi-instance app)
CREATE TABLE circuit_breaker_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(128) NOT NULL UNIQUE COMMENT 'paymentGateway, stripeApi, və s.',
    state VARCHAR(16) NOT NULL DEFAULT 'CLOSED' COMMENT 'CLOSED | OPEN | HALF_OPEN',
    failure_count INT NOT NULL DEFAULT 0,
    last_failure_at TIMESTAMP NULL,
    next_attempt_at TIMESTAMP NULL,
    last_state_change_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cb_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
