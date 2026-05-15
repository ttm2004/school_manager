-- ============================================================
-- Admission Rounds Migration
-- Chạy trong phpMyAdmin trên database: edu_management
-- ============================================================

CREATE TABLE IF NOT EXISTS `admission_rounds` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `year`                YEAR NOT NULL,
    `name`                VARCHAR(200) NOT NULL COMMENT 'VD: Tuyển sinh đại học 2025',

    -- Đợt chính thức
    `reg_start`           DATETIME NOT NULL COMMENT 'Bắt đầu nhận hồ sơ',
    `reg_end`             DATETIME NOT NULL COMMENT 'Kết thúc nhận hồ sơ',
    `review_start`        DATETIME NOT NULL COMMENT 'Bắt đầu xét tuyển',
    `review_end`          DATETIME NOT NULL COMMENT 'Kết thúc xét tuyển',
    `enroll_deadline`     DATETIME NOT NULL COMMENT 'Hạn cuối làm thủ tục nhập học',

    -- Đợt bổ sung (nullable - không bắt buộc)
    `supp_reg_start`      DATETIME NULL COMMENT 'Bắt đầu nhận hồ sơ bổ sung',
    `supp_reg_end`        DATETIME NULL COMMENT 'Kết thúc nhận hồ sơ bổ sung',
    `supp_review_end`     DATETIME NULL COMMENT 'Kết thúc xét bổ sung',
    `supp_enroll_deadline` DATETIME NULL COMMENT 'Hạn nhập học bổ sung',
    `supp_score_bonus`    DECIMAL(4,2) DEFAULT 0 COMMENT 'Điểm chuẩn bổ sung cao hơn bao nhiêu so với chính thức',

    `status`              ENUM('draft','open','reviewing','results','enrolling','supplementary','completed') DEFAULT 'draft',
    `notes`               TEXT,
    `created_by`          INT COMMENT 'user_id admin tạo',
    `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `year_unique` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Thêm cột round_id vào admission_applications (nếu chưa có)
ALTER TABLE `admission_applications`
    ADD COLUMN IF NOT EXISTS `round_id` INT NULL COMMENT 'Thuộc đợt tuyển sinh nào',
    ADD COLUMN IF NOT EXISTS `is_supplementary` TINYINT(1) DEFAULT 0 COMMENT '1 = hồ sơ bổ sung';

-- Seed: đợt tuyển sinh mẫu năm hiện tại
INSERT IGNORE INTO `admission_rounds`
    (`year`, `name`, `reg_start`, `reg_end`, `review_start`, `review_end`, `enroll_deadline`, `status`)
VALUES (
    YEAR(NOW()),
    CONCAT('Tuyển sinh đại học ', YEAR(NOW())),
    CONCAT(YEAR(NOW()), '-03-01 00:00:00'),
    CONCAT(YEAR(NOW()), '-07-31 23:59:59'),
    CONCAT(YEAR(NOW()), '-08-01 00:00:00'),
    CONCAT(YEAR(NOW()), '-08-20 23:59:59'),
    CONCAT(YEAR(NOW()), '-09-15 23:59:59'),
    'open'
);
