SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `system_notifications` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'general',
    `ref_id` INT NULL DEFAULT NULL,
    `ref_type` VARCHAR(50) NULL DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_system_notifications_user_read` (`user_id`, `is_read`),
    KEY `idx_system_notifications_created` (`created_at`),
    CONSTRAINT `fk_system_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `notifications`
    DROP COLUMN IF EXISTS `user_id`,
    DROP COLUMN IF EXISTS `is_read`;
