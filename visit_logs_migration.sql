-- ============================================================
-- Visit Logs Migration
-- Chạy trong phpMyAdmin trên database: edu_management
-- ============================================================

CREATE TABLE IF NOT EXISTS `visit_logs` (
    `id`         BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT NULL COMMENT 'NULL = khách vãng lai',
    `role`       VARCHAR(20) NULL COMMENT 'admin, student, teacher, staff',
    `session_id` VARCHAR(128) NOT NULL COMMENT 'PHP session ID',
    `ip`         VARCHAR(45),
    `user_agent` TEXT,
    `login_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_seen`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    INDEX (`role`),
    INDEX (`login_at`),
    UNIQUE KEY `session_unique` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
