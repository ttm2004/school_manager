-- ============================================================
-- Faculty Module Upgrade Migration
-- Chạy trong phpMyAdmin → database edu_management
-- Thứ tự: sau khi đã chạy faculty_module_migration.sql
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ============================================================
-- BƯỚC 1A: Chuẩn hóa RBAC — thêm roles mới
-- ============================================================

-- Thêm cột faculty_id vào roles nếu chưa có
ALTER TABLE `roles`
    ADD COLUMN IF NOT EXISTS `faculty_id` INT NULL DEFAULT NULL
        COMMENT 'NULL = role cấp trường, > 0 = role thuộc khoa cụ thể';

-- Roles cấp trường mới
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`) VALUES
('dept_head',        'Trưởng Bộ môn',   'Khoa/Viện', 'Quản lý bộ môn, đề xuất phân công GV trong bộ môn', '#0d6efd', 1),
('faculty_lecturer', 'Giảng viên',       'Khoa/Viện', 'Xem lịch dạy, nguyện vọng giảng dạy, KPI cá nhân',  '#6c757d', 1);

-- Đổi tên role hiện tại cho rõ nghĩa hơn (cập nhật name, không đổi code)
UPDATE `roles` SET `name` = 'Trưởng Khoa/Viện' WHERE `code` = 'faculty_manager';
UPDATE `roles` SET `name` = 'Thư ký Khoa/Viện' WHERE `code` = 'faculty_staff';

-- Permissions cho dept_head
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, 'faculty', p.perm
FROM roles r
CROSS JOIN (
    SELECT 'view_teachers'        AS perm UNION SELECT 'view_students'
    UNION SELECT 'view_curriculum'         UNION SELECT 'view_grades'
    UNION SELECT 'view_exam_schedules'     UNION SELECT 'view_reports'
    UNION SELECT 'manage_assignments'      UNION SELECT 'submit_proposals'
    UNION SELECT 'view_kpi'                UNION SELECT 'manage_dept_kpi'
) p
WHERE r.code = 'dept_head';

-- Permissions cho faculty_lecturer
INSERT IGNORE INTO `role_permissions` (`role_id`, `module`, `permission`)
SELECT r.id, 'faculty', p.perm
FROM roles r
CROSS JOIN (
    SELECT 'view_own_schedule'    AS perm
    UNION SELECT 'submit_teaching_wish'
    UNION SELECT 'view_own_kpi'
    UNION SELECT 'view_own_students'
) p
WHERE r.code = 'faculty_lecturer';

-- Roles khoa cụ thể cho dept_head (1 per faculty)
INSERT IGNORE INTO `roles` (`code`, `name`, `department`, `description`, `color`, `is_active`, `faculty_id`)
SELECT
    CONCAT('dept_head_', f.id),
    CONCAT('Trưởng Bộ môn - ', f.faculty_name),
    f.faculty_name,
    CONCAT('Trưởng Bộ môn thuộc ', f.faculty_name),
    '#0d6efd',
    1,
    f.id
FROM faculties f;

-- ============================================================
-- BƯỚC 1B: Thêm cột actor_role vào faculty_audit_logs
-- ============================================================
ALTER TABLE `faculty_audit_logs`
    ADD COLUMN IF NOT EXISTS `actor_role` VARCHAR(50) NULL DEFAULT NULL
        COMMENT 'Role của người thực hiện tại thời điểm thao tác'
    AFTER `user_id`;

-- ============================================================
-- BƯỚC 1C: Thêm cột mở rộng vào course_sections
-- ============================================================
ALTER TABLE `course_sections`
    ADD COLUMN IF NOT EXISTS `expected_students`  INT          NULL DEFAULT NULL
        COMMENT 'Dự kiến sĩ số',
    ADD COLUMN IF NOT EXISTS `room_requirement`   VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'Phòng học yêu cầu (VD: Lab máy tính, Phòng thực hành)',
    ADD COLUMN IF NOT EXISTS `teaching_mode`      ENUM('offline','online','hybrid')
                                                  NOT NULL DEFAULT 'offline'
        COMMENT 'Hình thức học',
    ADD COLUMN IF NOT EXISTS `special_equipment`  TEXT         NULL DEFAULT NULL
        COMMENT 'Thiết bị đặc biệt cần thiết',
    ADD COLUMN IF NOT EXISTS `open_proposal_note` TEXT         NULL DEFAULT NULL
        COMMENT 'Ghi chú đề xuất mở lớp từ Khoa';

-- ============================================================
-- BƯỚC 2A: Bảng teaching_wishes — Nguyện vọng giảng dạy
-- ============================================================
CREATE TABLE IF NOT EXISTS `teaching_wishes` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `teacher_id`      INT             NOT NULL COMMENT 'teachers.id',
    `subject_id`      INT             NOT NULL COMMENT 'subjects.id',
    `semester_id`     INT             NOT NULL COMMENT 'semesters.id',
    `faculty_id`      INT             NOT NULL COMMENT 'Khoa của GV (để filter)',
    `department_id`   INT             NULL DEFAULT NULL COMMENT 'Bộ môn của GV',
    `priority`        TINYINT         NOT NULL DEFAULT 1 COMMENT '1=Ưu tiên cao, 2=Bình thường, 3=Thấp',
    `note`            TEXT            NULL COMMENT 'Ghi chú của GV',
    -- Trạng thái workflow
    `status`          ENUM(
                        'pending',    -- GV vừa đăng ký, chờ Trưởng BM xem xét
                        'dept_approved',  -- Trưởng BM đồng ý, chờ Trưởng khoa duyệt
                        'dept_rejected',  -- Trưởng BM từ chối
                        'faculty_approved', -- Trưởng khoa duyệt, chờ Phòng ĐT chốt
                        'faculty_rejected', -- Trưởng khoa từ chối
                        'confirmed',  -- Phòng ĐT chốt chính thức
                        'cancelled'   -- GV hủy
                      ) NOT NULL DEFAULT 'pending',
    `dept_reviewed_by`    INT         NULL DEFAULT NULL COMMENT 'Trưởng BM duyệt (user_id)',
    `dept_reviewed_at`    TIMESTAMP   NULL DEFAULT NULL,
    `dept_note`           TEXT        NULL,
    `faculty_reviewed_by` INT         NULL DEFAULT NULL COMMENT 'Trưởng khoa duyệt (user_id)',
    `faculty_reviewed_at` TIMESTAMP   NULL DEFAULT NULL,
    `faculty_note`        TEXT        NULL,
    `created_at`          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wish_teacher_subject_sem` (`teacher_id`, `subject_id`, `semester_id`),
    INDEX `idx_wish_faculty`    (`faculty_id`),
    INDEX `idx_wish_dept`       (`department_id`),
    INDEX `idx_wish_semester`   (`semester_id`),
    INDEX `idx_wish_status`     (`status`),
    CONSTRAINT `fk_wish_teacher`   FOREIGN KEY (`teacher_id`)  REFERENCES `teachers`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_wish_subject`   FOREIGN KEY (`subject_id`)  REFERENCES `subjects`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_wish_semester`  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Nguyện vọng giảng dạy của GV theo học kỳ';

-- ============================================================
-- BƯỚC 3A: Mở rộng cảnh báo học vụ SV
-- ============================================================

-- Thêm cột chuẩn ngoại ngữ vào students (nếu chưa có)
ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `english_cert`       VARCHAR(50)  NULL DEFAULT NULL
        COMMENT 'Chứng chỉ ngoại ngữ (IELTS, TOEIC, B1, B2...)',
    ADD COLUMN IF NOT EXISTS `english_cert_score` VARCHAR(20)  NULL DEFAULT NULL
        COMMENT 'Điểm/mức chứng chỉ',
    ADD COLUMN IF NOT EXISTS `enrollment_year`    YEAR         NULL DEFAULT NULL
        COMMENT 'Năm nhập học (để tính tiến độ tốt nghiệp)',
    ADD COLUMN IF NOT EXISTS `expected_grad_year` YEAR         NULL DEFAULT NULL
        COMMENT 'Năm dự kiến tốt nghiệp';

-- Bảng graduation_requirements — Điều kiện tốt nghiệp theo ngành
CREATE TABLE IF NOT EXISTS `graduation_requirements` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `major_id`            INT          NOT NULL COMMENT 'majors.id',
    `min_credits`         INT          NOT NULL DEFAULT 120 COMMENT 'Tổng tín chỉ tối thiểu',
    `min_gpa`             DECIMAL(4,2) NOT NULL DEFAULT 5.00 COMMENT 'GPA tối thiểu (thang 10)',
    `require_english`     TINYINT(1)   NOT NULL DEFAULT 1   COMMENT 'Bắt buộc chứng chỉ ngoại ngữ',
    `min_english_level`   VARCHAR(20)  NULL DEFAULT 'B1'    COMMENT 'Mức tối thiểu (B1, B2, IELTS 5.5...)',
    `require_thesis`      TINYINT(1)   NOT NULL DEFAULT 0   COMMENT 'Bắt buộc khóa luận',
    `note`                TEXT         NULL,
    `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_grad_req_major` (`major_id`),
    CONSTRAINT `fk_grad_req_major` FOREIGN KEY (`major_id`) REFERENCES `majors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Điều kiện tốt nghiệp theo ngành';

-- ============================================================
-- BƯỚC 4A: KPI Giảng viên
-- ============================================================

-- Bảng teacher_kpi_periods — Kỳ đánh giá KPI (thường theo năm học)
CREATE TABLE IF NOT EXISTS `teacher_kpi_periods` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `period_name` VARCHAR(100) NOT NULL COMMENT 'VD: Năm học 2025-2026',
    `year_start`  YEAR         NOT NULL,
    `year_end`    YEAR         NOT NULL,
    `status`      ENUM('open','closed') NOT NULL DEFAULT 'open',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Kỳ đánh giá KPI giảng viên';

-- Bảng teacher_kpi — KPI từng GV theo kỳ
CREATE TABLE IF NOT EXISTS `teacher_kpi` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `teacher_id`            INT          NOT NULL COMMENT 'teachers.id',
    `period_id`             INT UNSIGNED NOT NULL COMMENT 'teacher_kpi_periods.id',
    `faculty_id`            INT          NOT NULL COMMENT 'Để filter theo khoa',
    -- Giảng dạy
    `teaching_hours_plan`   INT          NOT NULL DEFAULT 0 COMMENT 'Giờ giảng kế hoạch',
    `teaching_hours_actual` INT          NOT NULL DEFAULT 0 COMMENT 'Giờ giảng thực tế',
    -- Nghiên cứu khoa học
    `research_projects`     INT          NOT NULL DEFAULT 0 COMMENT 'Số đề tài NCKH',
    `papers_published`      INT          NOT NULL DEFAULT 0 COMMENT 'Số bài báo đã đăng',
    `papers_in_progress`    INT          NOT NULL DEFAULT 0 COMMENT 'Số bài báo đang viết',
    -- Hướng dẫn
    `thesis_supervised`     INT          NOT NULL DEFAULT 0 COMMENT 'Số khóa luận hướng dẫn',
    `project_graded`        INT          NOT NULL DEFAULT 0 COMMENT 'Số đồ án chấm',
    -- Đào tạo bản thân
    `training_courses`      INT          NOT NULL DEFAULT 0 COMMENT 'Số khóa bồi dưỡng tham gia',
    -- Ghi chú & trạng thái
    `note`                  TEXT         NULL,
    `status`                ENUM('draft','submitted','approved') NOT NULL DEFAULT 'draft',
    `submitted_at`          TIMESTAMP    NULL DEFAULT NULL,
    `approved_by`           INT          NULL DEFAULT NULL COMMENT 'user_id Trưởng khoa duyệt',
    `approved_at`           TIMESTAMP    NULL DEFAULT NULL,
    `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_kpi_teacher_period` (`teacher_id`, `period_id`),
    INDEX `idx_kpi_faculty`  (`faculty_id`),
    INDEX `idx_kpi_period`   (`period_id`),
    CONSTRAINT `fk_kpi_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_kpi_period`  FOREIGN KEY (`period_id`)  REFERENCES `teacher_kpi_periods`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='KPI giảng viên theo kỳ đánh giá';

-- ============================================================
-- Seed: KPI period mặc định
-- ============================================================
INSERT IGNORE INTO `teacher_kpi_periods` (`period_name`, `year_start`, `year_end`, `status`)
VALUES ('Năm học 2025-2026', 2025, 2026, 'open');

-- ============================================================
-- Indexes hiệu năng
-- ============================================================
CREATE INDEX IF NOT EXISTS `idx_teaching_wishes_teacher`  ON `teaching_wishes` (`teacher_id`);
CREATE INDEX IF NOT EXISTS `idx_teaching_wishes_faculty`  ON `teaching_wishes` (`faculty_id`);
CREATE INDEX IF NOT EXISTS `idx_teacher_kpi_teacher`      ON `teacher_kpi` (`teacher_id`);
CREATE INDEX IF NOT EXISTS `idx_teacher_kpi_faculty`      ON `teacher_kpi` (`faculty_id`);

-- ============================================================
-- Kiểm tra kết quả
-- ============================================================
SELECT 'teaching_wishes'         AS tbl, COUNT(*) AS rows FROM teaching_wishes
UNION ALL SELECT 'teacher_kpi_periods', COUNT(*) FROM teacher_kpi_periods
UNION ALL SELECT 'teacher_kpi',         COUNT(*) FROM teacher_kpi
UNION ALL SELECT 'graduation_requirements', COUNT(*) FROM graduation_requirements;

SELECT r.code, r.name, r.faculty_id
FROM roles r
WHERE r.code IN ('dept_head','faculty_lecturer','faculty_manager','faculty_staff')
   OR r.code LIKE 'dept_head_%'
ORDER BY r.faculty_id IS NULL DESC, r.faculty_id, r.code;

SET foreign_key_checks = 1;
