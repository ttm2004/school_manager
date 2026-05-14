CREATE TABLE IF NOT EXISTS `visit_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `role` VARCHAR(20) NULL,
    `session_id` VARCHAR(128) NOT NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `login_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    INDEX (`role`),
    INDEX (`login_at`),
    UNIQUE KEY `session_unique` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `visit_logs`
    ADD COLUMN IF NOT EXISTS `active_role` VARCHAR(80) NULL AFTER `role`,
    ADD COLUMN IF NOT EXISTS `current_path` VARCHAR(255) NULL AFTER `user_agent`,
    ADD COLUMN IF NOT EXISTS `current_module` VARCHAR(50) NULL AFTER `current_path`,
    ADD COLUMN IF NOT EXISTS `device` VARCHAR(20) NULL AFTER `current_module`,
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `last_seen`,
    ADD COLUMN IF NOT EXISTS `logout_at` DATETIME NULL AFTER `is_active`,
    ADD INDEX IF NOT EXISTS `idx_visit_online` (`is_active`, `last_seen`),
    ADD INDEX IF NOT EXISTS `idx_visit_user_seen` (`user_id`, `last_seen`),
    ADD INDEX IF NOT EXISTS `idx_visit_module` (`current_module`);

UPDATE `visit_logs`
SET `is_active` = CASE
    WHEN `last_seen` >= NOW() - INTERVAL 15 MINUTE THEN 1
    ELSE 0
END
WHERE `is_active` IS NULL OR `logout_at` IS NULL;
