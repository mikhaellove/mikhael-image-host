CREATE TABLE IF NOT EXISTS ip_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    failed_attempts INT NOT NULL DEFAULT 0,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    window_start TIMESTAMP NULL,
    blocked_at TIMESTAMP NULL,
    block_expires_at TIMESTAMP NULL,
    last_attempt_at TIMESTAMP NULL,
    UNIQUE KEY uq_ip (ip_address),
    KEY idx_blocked (is_blocked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
