-- ============================================================
-- Faculty/Department Management Module — Migration
-- File: university/faculty_module_migration.sql
-- Chạy 1 lần trong phpMyAdmin hoặc MySQL CLI
-- Yêu cầu: faculty_proposal_migration.sql và faculty_roles_migration.sql
--          đã được chạy trước
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ============================================================
-- 1. Bảng faculty_audit_logs — Nhật ký thao tác (Req 16)
-- ============================================================
CREATE TABLE IF NOT EXISTS `faculty_audit_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT NOT NULL                    COMMENT 'user_id của người thực hiện',
    `action_type` ENUM(
                    'create','update','delete',
                    'submit','approve','reject',
                    'restore','export','login_denied'
                  ) NOT NULL                      COMMENT 'Loại hành động',
    `module`      VARCHAR(50)  NOT NULL DEFAULT 'faculty'
                                                  COMMENT 'Module thực hiện',
    `table_name`  VARCHAR(64)  NOT NULL            COMMENT 'Bảng bị tác động',
    `record_id`   INT          NOT NULL DEFAULT 0  COMMENT 'ID của record bị tác động',
    `old_data`    TEXT         NULL                COMMENT 'Dữ liệu trước khi thay đổi (JSON)',
    `new_data`    TEXT         NULL                COMMENT 'Dữ liệu sau khi thay đổi (JSON)',
    `ip_address`  VARCHAR(45)  NOT NULL DEFAULT '' COMMENT 'IP của người thực hiện',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_user`    (`user_id`),
    INDEX `idx_audit_table`   (`table_name`, `record_id`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Nhật ký thao tác module Khoa/Viện';

-- ============================================================
-- 2. Bảng departments — Bộ môn trong khoa (Req 26)
-- ============================================================
CREATE TABLE IF NOT EXISTS `departments` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `faculty_id`      INT          NOT NULL        COMMENT 'Khoa chứa bộ môn (faculties.id)',
    `department_code` VARCHAR(20)  NOT NULL        COMMENT 'Mã bộ môn (VD: BM_CNPM)',
    `department_name` VARCHAR(200) NOT NULL        COMMENT 'Tên bộ môn',
    `head_teacher_id` INT          NULL DEFAULT NULL
                                                  COMMENT 'Trưởng bộ môn (teachers.id)',
    `description`     TEXT         NULL,
    `deleted_at`      TIMESTAMP    NULL DEFAULT NULL
                                                  COMMENT 'Soft delete timestamp',
    `deleted_by`      INT          NULL DEFAULT NULL
                                                  COMMENT 'user_id người xóa',
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dept_code_faculty` (`faculty_id`, `department_code`),
    INDEX `idx_dept_faculty` (`faculty_id`),
    CONSTRAINT `fk_dept_faculty`
        FOREIGN KEY (`faculty_id`) REFERENCES `faculties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bộ môn trong khoa';

-- ============================================================
-- 3. Bảng student_warnings — Ghi chú cảnh báo học vụ (Req 17)
-- ============================================================
CREATE TABLE IF NOT EXISTS `student_warnings` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`   INT          NOT NULL        COMMENT 'students.id',
    `faculty_id`   INT          NOT NULL        COMMENT 'Khoa quản lý (để filter)',
    `warning_type` ENUM('gpa','credits','retake','manual')
                               NOT NULL        COMMENT 'Loại cảnh báo',
    `note`         TEXT         NULL            COMMENT 'Ghi chú can thiệp của Trưởng khoa',
    `created_by`   INT          NOT NULL        COMMENT 'user_id người tạo ghi chú',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sw_student`  (`student_id`),
    INDEX `idx_sw_faculty`  (`faculty_id`),
    INDEX `idx_sw_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ghi chú cảnh báo và can thiệp học vụ';

-- ============================================================
-- 4. ALTER course_sections — thêm 'draft' vào ENUM status (Req 15)
--    Lưu ý: faculty_proposal_migration.sql đã thêm 'proposed'
--    Ta chỉ cần thêm 'draft' vào ENUM
-- ============================================================
ALTER TABLE `course_sections`
    MODIFY COLUMN `status`
        ENUM('open','closed','cancelled','proposed','draft')
        NOT NULL DEFAULT 'open'
        COMMENT 'open=đang mở, closed=đã đóng, cancelled=hủy, proposed=Khoa đề xuất (chờ duyệt), draft=nháp chưa gửi';

-- Thêm cột submitted_at và submitted_by để track workflow (Req 15)
ALTER TABLE `course_sections`
    ADD COLUMN IF NOT EXISTS `open_submitted_at` TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Thời điểm Khoa gửi đề xuất mở lớp (draft → pending)',
    ADD COLUMN IF NOT EXISTS `open_submitted_by` INT NULL DEFAULT NULL
        COMMENT 'user_id người gửi đề xuất mở lớp',
    ADD COLUMN IF NOT EXISTS `open_reviewed_by`  INT NULL DEFAULT NULL
        COMMENT 'user_id Phòng ĐT duyệt/từ chối đề xuất mở lớp',
    ADD COLUMN IF NOT EXISTS `open_reviewed_at`  TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Thời điểm Phòng ĐT duyệt/từ chối',
    ADD COLUMN IF NOT EXISTS `open_reject_reason` TEXT NULL DEFAULT NULL
        COMMENT 'Lý do từ chối đề xuất mở lớp (nếu rejected)';

-- Thêm 'draft' vào proposal_status ENUM cho teacher assignment (Req 15)
ALTER TABLE `course_sections`
    MODIFY COLUMN `proposal_status`
        ENUM('draft','pending','approved','rejected')
        NULL DEFAULT NULL
        COMMENT 'Trạng thái đề xuất phân công GV: draft=nháp, pending=chờ duyệt, approved=đã duyệt, rejected=từ chối';

-- ============================================================
-- 5. ALTER curriculum — thêm soft delete columns (Req 25)
-- ============================================================
ALTER TABLE `curriculum`
    ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Soft delete timestamp',
    ADD COLUMN IF NOT EXISTS `deleted_by` INT NULL DEFAULT NULL
        COMMENT 'user_id người xóa';

-- ============================================================
-- 6. ALTER teachers — thêm department_id (Req 26)
-- ============================================================
ALTER TABLE `teachers`
    ADD COLUMN IF NOT EXISTS `department_id` INT NULL DEFAULT NULL
        COMMENT 'Bộ môn trong khoa (departments.id), nullable — không bắt buộc';

-- ============================================================
-- 7. Indexes hiệu năng (Req 22)
--    Dùng CREATE INDEX IF NOT EXISTS (MariaDB 10.1.4+)
-- ============================================================

-- teachers.faculty_id — filter GV theo khoa
CREATE INDEX IF NOT EXISTS `idx_teachers_faculty`
    ON `teachers` (`faculty_id`);

-- students.major_id — filter SV theo ngành
CREATE INDEX IF NOT EXISTS `idx_students_major`
    ON `students` (`major_id`);

-- course_sections(semester_id, status) — filter lớp HP theo HK + trạng thái
CREATE INDEX IF NOT EXISTS `idx_sections_semester_status`
    ON `course_sections` (`semester_id`, `status`);

-- grades.course_section_id — JOIN grades với sections
CREATE INDEX IF NOT EXISTS `idx_grades_section`
    ON `grades` (`course_section_id`);

-- curriculum.major_id — filter CTĐT theo ngành
CREATE INDEX IF NOT EXISTS `idx_curriculum_major`
    ON `curriculum` (`major_id`);

-- curriculum.deleted_at — exclude soft-deleted entries
CREATE INDEX IF NOT EXISTS `idx_curriculum_deleted`
    ON `curriculum` (`deleted_at`);

-- faculty_audit_logs.user_id — filter log theo user
-- (đã tạo trong CREATE TABLE ở trên)

-- departments.faculty_id — filter bộ môn theo khoa
-- (đã tạo trong CREATE TABLE ở trên)

-- ============================================================
-- 8. Kiểm tra kết quả
-- ============================================================
SELECT 'faculty_audit_logs' AS tbl, COUNT(*) AS rows FROM faculty_audit_logs
UNION ALL
SELECT 'departments',  COUNT(*) FROM departments
UNION ALL
SELECT 'student_warnings', COUNT(*) FROM student_warnings;

DESCRIBE course_sections;
DESCRIBE curriculum;
DESCRIBE teachers;

SET foreign_key_checks = 1;
