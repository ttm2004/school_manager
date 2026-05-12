-- Migration 007: Audit Logs chuẩn enterprise
-- Thay thế/bổ sung cho faculty_audit_logs

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT             NULL DEFAULT NULL     COMMENT 'NULL = system action',
    `user_role`   VARCHAR(50)     NOT NULL DEFAULT ''   COMMENT 'Role tại thời điểm thao tác',
    `action`      VARCHAR(50)     NOT NULL              COMMENT 'create|update|delete|view|export|approve|reject|login|logout',
    `entity_type` VARCHAR(64)     NOT NULL              COMMENT 'Tên bảng/entity: course_section, grade, user...',
    `entity_id`   INT             NOT NULL DEFAULT 0    COMMENT 'ID của record bị tác động',
    `old_data`    JSON            NULL DEFAULT NULL     COMMENT 'Dữ liệu trước khi thay đổi',
    `new_data`    JSON            NULL DEFAULT NULL     COMMENT 'Dữ liệu sau khi thay đổi',
    `ip`          VARCHAR(45)     NOT NULL DEFAULT ''   COMMENT 'IP address (IPv4/IPv6)',
    `user_agent`  VARCHAR(500)    NOT NULL DEFAULT ''   COMMENT 'Browser/client info',
    `request_id`  VARCHAR(32)     NOT NULL DEFAULT ''   COMMENT 'Unique request ID để trace',
    `module`      VARCHAR(50)     NOT NULL DEFAULT 'system' COMMENT 'Module: faculty|academic|admissions|auth...',
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_user`       (`user_id`),
    INDEX `idx_audit_entity`     (`entity_type`, `entity_id`),
    INDEX `idx_audit_action`     (`action`),
    INDEX `idx_audit_module`     (`module`),
    INDEX `idx_audit_created`    (`created_at`),
    INDEX `idx_audit_request`    (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit log chuẩn enterprise — ghi lại mọi thao tác quan trọng';

SELECT 'Migration 007: audit_logs created' AS status;
