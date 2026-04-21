CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36) NULL,
    correlation_id VARCHAR(64) NULL,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NULL,
    entity_id VARCHAR(64) NULL,
    method VARCHAR(8) NOT NULL,
    uri VARCHAR(512) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    request_payload JSON NULL,
    response_status SMALLINT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id, created_at),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_correlation (correlation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
