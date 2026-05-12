-- ============================================================
-- Tạo bảng curriculum cho module Faculty/Department Management
-- Chạy file này trong phpMyAdmin hoặc MySQL CLI
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `curriculum` (
    `id`                 INT(11)      NOT NULL AUTO_INCREMENT,
    `major_id`           INT(11)      NOT NULL                COMMENT 'FK → majors.id',
    `subject_id`         INT(11)      NOT NULL                COMMENT 'FK → subjects.id',
    `credits`            INT(11)      NOT NULL DEFAULT 3      COMMENT 'Số tín chỉ trong CTĐT',
    `suggested_semester` TINYINT(4)   NOT NULL DEFAULT 1      COMMENT 'Học kỳ đề xuất (1-8)',
    `subject_type`       ENUM('required','elective','general')
                                      NOT NULL DEFAULT 'required'
                                                              COMMENT 'Loại môn: bắt buộc/tự chọn/đại cương',
    `prerequisite_ids`   TEXT         NULL DEFAULT NULL       COMMENT 'Danh sách subject_id tiên quyết, phân cách bằng dấu phẩy',
    `deleted_at`         TIMESTAMP    NULL DEFAULT NULL       COMMENT 'Soft delete timestamp',
    `deleted_by`         INT(11)      NULL DEFAULT NULL       COMMENT 'user_id người xóa',
    `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_curriculum_major_subject` (`major_id`, `subject_id`),
    INDEX `idx_curriculum_major`   (`major_id`),
    INDEX `idx_curriculum_subject` (`subject_id`),
    INDEX `idx_curriculum_deleted` (`deleted_at`),
    CONSTRAINT `fk_curriculum_major`
        FOREIGN KEY (`major_id`)   REFERENCES `majors`(`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_curriculum_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Chương trình đào tạo: liên kết ngành học với môn học';

-- Kiểm tra kết quả
SELECT 'curriculum' AS tbl, COUNT(*) AS rows FROM curriculum;
DESCRIBE curriculum;
