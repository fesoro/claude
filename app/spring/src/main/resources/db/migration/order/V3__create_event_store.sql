-- Laravel: EloquentEventStore → event_store (Event Sourcing üçün)
-- Spring-də Axon Framework bunu öz cədvəlləri ilə də idarə edə bilər,
-- amma manual schema açıq saxlayırıq ki, Laravel ilə müqayisə oxşar olsun.
CREATE TABLE event_store (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id CHAR(36) NOT NULL UNIQUE,
    aggregate_id CHAR(36) NOT NULL,
    aggregate_type VARCHAR(128) NOT NULL,
    event_type VARCHAR(128) NOT NULL,
    event_version SMALLINT NOT NULL DEFAULT 1 COMMENT 'schema versioning üçün (Upcaster)',
    event_data JSON NOT NULL COMMENT 'event payload',
    event_metadata JSON NULL COMMENT 'correlation_id, causation_id, user_id',
    sequence_number BIGINT NOT NULL COMMENT 'aggregate-də neçəncidir',
    occurred_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_aggregate_version (aggregate_id, sequence_number) COMMENT 'optimistic locking',
    INDEX idx_es_aggregate (aggregate_id, sequence_number),
    INDEX idx_es_type (aggregate_type, event_type),
    INDEX idx_es_occurred (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
