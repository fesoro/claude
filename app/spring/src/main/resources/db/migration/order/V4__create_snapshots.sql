-- Laravel: SnapshotStore → snapshots (event replay-i sürətləndirmək üçün)
CREATE TABLE snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aggregate_id CHAR(36) NOT NULL,
    aggregate_type VARCHAR(128) NOT NULL,
    sequence_number BIGINT NOT NULL,
    snapshot_data JSON NOT NULL COMMENT 'serialized aggregate state',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_snap_aggregate_seq (aggregate_id, sequence_number),
    INDEX idx_snap_aggregate (aggregate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
