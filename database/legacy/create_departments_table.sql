-- ============================================================
-- Tạo bảng departments và thêm cột department_id vào teachers
-- Chạy file này trong phpMyAdmin hoặc MySQL CLI
-- ============================================================

SET NAMES utf8mb4;

-- 1. Tạo bảng departments (bộ môn trong khoa)
CREATE TABLE IF NOT EXISTS `departments` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `faculty_id`      INT          NOT NULL        COMMENT 'FK → faculties.id',
    `department_code` VARCHAR(20)  NOT NULL        COMMENT 'Mã bộ môn (VD: BM_CNPM)',
    `department_name` VARCHAR(200) NOT NULL        COMMENT 'Tên bộ môn',
    `head_teacher_id` INT          NULL DEFAULT NULL
                                                  COMMENT 'Trưởng bộ môn (teachers.id)',
    `description`     TEXT         NULL,
    `deleted_at`      TIMESTAMP    NULL DEFAULT NULL,
    `deleted_by`      INT          NULL DEFAULT NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dept_code_faculty` (`faculty_id`, `department_code`),
    INDEX `idx_dept_faculty` (`faculty_id`),
    CONSTRAINT `fk_dept_faculty`
        FOREIGN KEY (`faculty_id`) REFERENCES `faculties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bộ môn trong khoa';

-- 2. Thêm cột department_id vào bảng teachers (nếu chưa có)
ALTER TABLE `teachers`
    ADD COLUMN IF NOT EXISTS `department_id` INT NULL DEFAULT NULL
        COMMENT 'Bộ môn trong khoa (departments.id), nullable';

-- Kiểm tra kết quả
SELECT 'departments' AS tbl, COUNT(*) AS rows FROM departments;
DESCRIBE teachers;
