-- Additive migration. Does not reset users, catalog, orders or sales.
CREATE TABLE IF NOT EXISTS vr_sync (
 id INT PRIMARY KEY,
 source_id CHAR(36) NOT NULL,
 store_id INT NOT NULL,
 sequence_no BIGINT NOT NULL,
 batch_id CHAR(36) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 captured_at VARCHAR(50) NOT NULL,
 received_epoch BIGINT NOT NULL,
 payload LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
