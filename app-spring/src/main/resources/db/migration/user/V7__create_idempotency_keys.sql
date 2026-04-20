-- Laravel: IdempotencyMiddleware → idempotency_keys cədvəli (24h TTL)
-- Spring-də bunu Redis-də də saxlamaq mümkündür, amma audit üçün DB-də qalır
CREATE TABLE idempotency_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idempotency_key VARCHAR(128) NOT NULL UNIQUE,
    user_id CHAR(36) NULL,
    request_hash CHAR(64) NOT NULL COMMENT 'SHA-256 of payload',
    response_status SMALLINT NULL,
    response_body JSON NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ik_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
